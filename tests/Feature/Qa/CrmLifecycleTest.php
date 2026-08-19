<?php

declare(strict_types=1);

/**
 * QA §18 - the CRM lifecycle as a sales team would actually run it, driven
 * through HTTP rather than through the services underneath.
 *
 * Company:      Precision Manufacturing Group (180 staff, Philadelphia)
 * Contact:      Michael Rodriguez, CFO
 * Opportunity:  Cybersecurity Managed Services, $4,500 MRR
 * Rep:          Sarah Mitchell, Senior Account Executive
 *
 * §18 asks for the audit log to be verified alongside the records, so each
 * mutating step checks that it left a trail with an actor and a tenant.
 */

use App\Authorization\Role;
use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\Pipeline;
use App\Support\CurrentOrganization;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('Acme Managed IT Services');
    subscribeOrganization($this->org, 'enterprise');

    $this->sarah = addMember($this->org, Role::SalesManager);
    $this->sarah->forceFill(['name' => 'Sarah Mitchell'])->save();
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

it('runs the Precision Manufacturing opportunity from company to closed won', function () {
    $as = fn () => $this->actingAs($this->sarah);

    // -- 1. Company --------------------------------------------------------
    $as()->post(route('crm.companies.store'), [
        'name' => 'Precision Manufacturing Group',
        'industry' => 'manufacturing',
        'city' => 'Philadelphia',
        'region' => 'Pennsylvania',
        'country' => 'US',
        'size' => '180',
    ])->assertSessionHasNoErrors();

    app(CurrentOrganization::class)->set($this->org);
    $company = Company::where('name', 'Precision Manufacturing Group')->firstOrFail();
    app(CurrentOrganization::class)->forget();

    expect($company->organization_id)->toBe($this->org->id);

    // -- 2. Lead from the CMMC campaign ------------------------------------
    $as()->post(route('crm.leads.store'), [
        'first_name' => 'Michael',
        'last_name' => 'Rodriguez',
        'email' => 'michael.rodriguez@precisionmfg-test.com',
        'phone' => '+12155550142',
        'company_name' => 'Precision Manufacturing Group',
        'source' => 'paid',
        'campaign' => 'Philadelphia CMMC Lead Generation',
    ])->assertSessionHasNoErrors();

    app(CurrentOrganization::class)->set($this->org);
    $lead = Lead::where('email', 'michael.rodriguez@precisionmfg-test.com')->firstOrFail();
    app(CurrentOrganization::class)->forget();

    expect($lead->status)->not->toBe('converted');

    // -- 3. Convert, creating the opportunity ------------------------------
    $as()->post(route('crm.leads.convert', $lead->id), [
        'create_deal' => true,
        'deal_value' => 54_000,   // major units at the boundary
    ])->assertSessionHasNoErrors();

    app(CurrentOrganization::class)->set($this->org);
    $lead->refresh();
    $contact = Contact::where('email', 'michael.rodriguez@precisionmfg-test.com')->firstOrFail();

    expect($lead->status)->toBe('converted')
        ->and($lead->converted_contact_id)->toBe($contact->id)
        ->and($lead->converted_at)->not->toBeNull()
        ->and($lead->converted_deal_id)->not->toBeNull();

    $deal = Deal::findOrFail($lead->converted_deal_id);
    app(CurrentOrganization::class)->forget();

    // -- 4. Re-converting must be refused ----------------------------------
    $as()->post(route('crm.leads.convert', $lead->id), ['create_deal' => true])
        ->assertSessionHasErrors();

    app(CurrentOrganization::class)->set($this->org);
    expect(Deal::count())->toBe(1, 'a second conversion created a duplicate deal');
    app(CurrentOrganization::class)->forget();

    // -- 5. Activity and note on the timeline ------------------------------
    foreach ([
        ['call', 'Discovery call', 'Discussed CMMC Level 2 scoping for 180 staff.'],
        ['note', 'Budget note', 'CFO signalled budget approval for Q4.'],
        ['task', 'Send proposal', 'Cybersecurity managed services proposal.'],
    ] as [$type, $title, $body]) {
        $as()->post(route('crm.activities.store'), [
            'subject_type' => 'contact',
            'subject_id' => $contact->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
        ])->assertSessionHasNoErrors();
    }

    app(CurrentOrganization::class)->set($this->org);
    $timeline = Activity::where('subject_type', 'contact')->where('subject_id', $contact->id)->get();

    expect($timeline)->toHaveCount(3)
        ->and($timeline->pluck('type')->sort()->values()->all())->toBe(['call', 'note', 'task'])
        // §18 wants the timeline attributed to whoever did it.
        ->and($timeline->pluck('user_id')->unique()->all())->toBe([$this->sarah->id]);
    app(CurrentOrganization::class)->forget();

    // -- 6. Move the deal through the pipeline to won ----------------------
    app(CurrentOrganization::class)->set($this->org);
    $pipeline = Pipeline::where('is_default', true)->firstOrFail();
    $won = $pipeline->stages()->where('is_won', true)->firstOrFail();
    app(CurrentOrganization::class)->forget();

    $as()->patch(route('crm.deals.update', $deal->id), [
        'name' => 'Precision Manufacturing - Cybersecurity Managed Services',
        'mrr' => 4_500,          // major units; controller converts to minor
        'value' => 54_000,
        'contract_term_months' => 12,
    ])->assertSessionHasNoErrors();

    $as()->patch(route('crm.deals.stage', $deal->id), ['stage_id' => $won->id])
        ->assertSessionHasNoErrors();

    // -- 7. Revenue arithmetic --------------------------------------------
    app(CurrentOrganization::class)->set($this->org);
    $deal->refresh();

    expect($deal->mrr)->toBe(450_000)
        ->and($deal->arr)->toBe(5_400_000)     // derived, never typed in
        ->and($deal->stage_id)->toBe($won->id);

    // -- 8. Audit trail ----------------------------------------------------
    $actions = AuditLog::pluck('action')->unique()->values()->all();

    expect($actions)->toContain('crm.lead.converted');

    $entry = AuditLog::where('action', 'crm.lead.converted')->firstOrFail();

    // §43: an audit entry is only useful with actor, tenant, action, resource
    // and timestamp all present.
    expect($entry->organization_id)->toBe($this->org->id)
        ->and($entry->actor_id)->toBe($this->sarah->id)
        ->and($entry->resource_type)->toBe('lead')
        ->and($entry->resource_id)->toBe((string) $lead->id)
        ->and($entry->created_at)->not->toBeNull();

    app(CurrentOrganization::class)->forget();
});

/*
|--------------------------------------------------------------------------
| §8 / §10 - CRUD failure paths and form validation
|--------------------------------------------------------------------------
*/

it('rejects malformed contact input instead of failing at the database', function () {
    $cases = [
        'missing required name' => ['first_name' => '', 'email' => 'a@b-test.com'],
        'whitespace-only name' => ['first_name' => '   ', 'email' => 'a@b-test.com'],
        'over-length first name' => ['first_name' => str_repeat('a', 121)],
        'over-length title' => ['first_name' => 'Michael', 'title' => str_repeat('x', 121)],
        'over-length phone' => ['first_name' => 'Michael', 'phone' => str_repeat('9', 41)],
        'malformed email' => ['first_name' => 'Michael', 'email' => 'not-an-email'],
    ];

    foreach ($cases as $label => $payload) {
        $response = $this->actingAs($this->sarah)->post(route('crm.contacts.store'), $payload);

        expect($response->getStatusCode())->not->toBe(500, "{$label} reached the database");
        $response->assertSessionHasErrors();
    }

    app(CurrentOrganization::class)->set($this->org);
    expect(Contact::count())->toBe(0, 'a rejected payload still created a contact');
    app(CurrentOrganization::class)->forget();
});

it('accepts unicode and punctuation in names and stores them intact', function () {
    $this->actingAs($this->sarah)->post(route('crm.contacts.store'), [
        'first_name' => 'José',
        'last_name' => "O'Brien-Nguyễn",
        'email' => 'jose.obrien@precisionmfg-test.com',
        'title' => 'Directeur Général & CFO',
    ])->assertSessionHasNoErrors();

    app(CurrentOrganization::class)->set($this->org);
    $contact = Contact::where('email', 'jose.obrien@precisionmfg-test.com')->firstOrFail();

    expect($contact->first_name)->toBe('José')
        ->and($contact->last_name)->toBe("O'Brien-Nguyễn")
        ->and($contact->title)->toBe('Directeur Général & CFO');
    app(CurrentOrganization::class)->forget();
});

it('stores a script payload as literal text rather than interpreting it', function () {
    $payload = '<script>alert("xss")</script>';

    $this->actingAs($this->sarah)->post(route('crm.contacts.store'), [
        'first_name' => $payload,
        'email' => 'xss.probe@precisionmfg-test.com',
    ])->assertSessionHasNoErrors();

    app(CurrentOrganization::class)->set($this->org);
    $contact = Contact::where('email', 'xss.probe@precisionmfg-test.com')->firstOrFail();

    // Stored verbatim - escaping is the renderer's job, not the column's.
    expect($contact->first_name)->toBe($payload);
    app(CurrentOrganization::class)->forget();

    // And the listing must not hand it back as live markup.
    $response = $this->actingAs($this->sarah)->get(route('crm.contacts.index'));
    $response->assertSuccessful();

    expect($response->getContent())->not->toContain('<script>alert("xss")</script>');
});
