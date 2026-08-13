<?php

use App\Models\Contact;
use App\Models\ImportJob;
use App\Support\CurrentOrganization;
use Illuminate\Http\UploadedFile;

function csvFile(string $content): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'csv').'.csv';
    file_put_contents($path, $content);

    return new UploadedFile($path, 'contacts.csv', 'text/csv', null, true);
}

it('previews an import with validation and duplicate detection', function () {
    [$org, $owner] = makeOrganization();
    app(CurrentOrganization::class)->set($org);
    Contact::create(['first_name' => 'Existing', 'email' => 'dupe@example.com']);
    app(CurrentOrganization::class)->forget();

    $csv = "First Name,Last Name,Email\nAda,Byron,ada@example.com\nBad,Row,not-an-email\nDup,Licate,dupe@example.com\n";

    $this->actingAs($owner)
        ->post(route('crm.contacts.import.preview'), ['file' => csvFile($csv)])
        ->assertRedirect();

    $preview = session('import_preview');
    expect($preview['total'])->toBe(3)
        ->and($preview['valid'])->toBe(1)
        ->and($preview['invalid'])->toBe(1)
        ->and($preview['duplicates'])->toBe(1);
});

it('imports contacts and records history with an error report', function () {
    [$org, $owner] = makeOrganization();
    $csv = "First Name,Last Name,Email,Company\nAda,Byron,ada@example.com,Analytical Engines\nNoEmail,,,\nBad,,bad-email,\n";

    $this->actingAs($owner)
        ->post(route('crm.contacts.import.store'), ['file' => csvFile($csv)])
        ->assertRedirect(route('crm.contacts.index'));

    expect(Contact::withoutGlobalScope('tenant')->where('email', 'ada@example.com')->exists())->toBeTrue()
        ->and(App\Models\Company::withoutGlobalScope('tenant')->where('name', 'Analytical Engines')->exists())->toBeTrue();

    $job = ImportJob::withoutGlobalScope('tenant')->latest('id')->first();
    // Ada + NoEmail (no email → not a duplicate) import; Bad email fails.
    expect($job->imported)->toBe(2)->and($job->failed)->toBe(1)
        ->and($job->errors)->not->toBeNull();
});

it('exports contacts as CSV', function () {
    [$org, $owner] = makeOrganization();
    app(CurrentOrganization::class)->set($org);
    Contact::create(['first_name' => 'Export', 'last_name' => 'Me', 'email' => 'export@example.com']);
    app(CurrentOrganization::class)->forget();

    $response = $this->actingAs($owner)->get(route('crm.contacts.export'));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');
    $response->streamedContent();
    expect($response->streamedContent())->toContain('export@example.com');
});

it('finds crm records in global search, tenant-scoped', function () {
    [$orgA, $ownerA] = makeOrganization('A');
    [$orgB] = makeOrganization('B');
    app(CurrentOrganization::class)->set($orgA);
    Contact::create(['first_name' => 'Findable', 'last_name' => 'Person', 'email' => 'find@a.com']);
    app(CurrentOrganization::class)->set($orgB);
    Contact::create(['first_name' => 'Findable', 'last_name' => 'Other', 'email' => 'find@b.com']);
    app(CurrentOrganization::class)->forget();

    $response = $this->actingAs($ownerA)->getJson(route('search', ['q' => 'Findable']));

    $items = collect($response->json('groups'))->firstWhere('type', 'contacts')['items'] ?? [];
    expect(collect($items)->pluck('subtitle'))->toContain('find@a.com')->not->toContain('find@b.com');
});
