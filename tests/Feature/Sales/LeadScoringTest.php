<?php

use App\Models\Contact;
use App\Models\Organization;
use App\Models\ScoringRule;
use App\Models\User;
use App\Services\Sales\LeadScoringService;
use App\Support\CurrentOrganization;

/**
 * @return array{0: Organization, 1: User}
 */
function salesOrganization(string $name = 'Test Org'): array
{
    [$org, $owner] = makeOrganization($name);
    subscribeOrganization($org, 'professional'); // Professional includes `sales`

    return [$org, $owner];
}

it('scores a contact by summing matched active rules', function () {
    [$org] = salesOrganization();
    app(CurrentOrganization::class)->set($org);

    ScoringRule::create(['name' => 'Is MQL', 'category' => 'behavioral', 'attribute' => 'lifecycle_stage', 'operator' => 'equals', 'value' => 'mql', 'points' => 30, 'is_active' => true]);
    ScoringRule::create(['name' => 'Opted in', 'category' => 'behavioral', 'attribute' => 'email_opt_in', 'operator' => 'is_true', 'points' => 20, 'is_active' => true]);
    ScoringRule::create(['name' => 'Has company', 'category' => 'firmographic', 'attribute' => 'has_company', 'operator' => 'is_true', 'points' => 10, 'is_active' => false]); // inactive

    $contact = Contact::create(['first_name' => 'A', 'email' => 'a@x.com', 'lifecycle_stage' => 'mql', 'email_opt_in' => true]);
    $score = app(LeadScoringService::class)->scoreContact($contact);
    app(CurrentOrganization::class)->forget();

    expect($score)->toBe(50); // 30 + 20; inactive rule excluded
});

it('evaluates contains and gte operators', function () {
    [$org] = salesOrganization();
    app(CurrentOrganization::class)->set($org);

    ScoringRule::create(['name' => 'Decision maker', 'category' => 'demographic', 'attribute' => 'title', 'operator' => 'contains', 'value' => 'director', 'points' => 25, 'is_active' => true]);
    $contact = Contact::create(['first_name' => 'C', 'email' => 'c@x.com', 'title' => 'IT Director']);

    expect(app(LeadScoringService::class)->scoreContact($contact))->toBe(25);
    app(CurrentOrganization::class)->forget();
});

it('classifies lead temperature', function () {
    $service = app(LeadScoringService::class);

    expect($service->temperature(60))->toBe('hot')
        ->and($service->temperature(30))->toBe('warm')
        ->and($service->temperature(10))->toBe('cold');
});

it('promotes lifecycle to SQL at the threshold', function () {
    [$org] = salesOrganization();
    app(CurrentOrganization::class)->set($org);

    ScoringRule::create(['name' => 'Big', 'category' => 'behavioral', 'attribute' => 'lead_source', 'operator' => 'equals', 'value' => 'form', 'points' => 60, 'is_active' => true]);
    $contact = Contact::create(['first_name' => 'B', 'email' => 'b@x.com', 'lead_source' => 'form', 'lifecycle_stage' => 'lead']);
    app(LeadScoringService::class)->apply($contact);
    app(CurrentOrganization::class)->forget();

    $contact->refresh();
    expect($contact->lead_score)->toBe(60)->and($contact->lifecycle_stage)->toBe('sql');
});

it('recomputes all contacts via the controller', function () {
    [$org, $owner] = salesOrganization();
    app(CurrentOrganization::class)->set($org);
    ScoringRule::create(['name' => 'Opt', 'category' => 'behavioral', 'attribute' => 'email_opt_in', 'operator' => 'is_true', 'points' => 15, 'is_active' => true]);
    Contact::create(['first_name' => 'D', 'email' => 'd@x.com', 'email_opt_in' => true]);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($owner)->post(route('sales.scoring.recompute'))->assertRedirect();

    expect(Contact::withoutGlobalScope('tenant')->firstWhere('email', 'd@x.com')->lead_score)->toBe(15);
});
