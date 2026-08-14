<?php

use App\Authorization\Role;
use App\Models\Company;
use App\Models\Contact;
use App\Models\SalesAsset;
use App\Models\ScoringRule;
use App\Models\TargetAccount;
use App\Services\Sales\AccountService;
use App\Support\CurrentOrganization;

it('adds an enablement asset via the controller', function () {
    [, $owner] = salesOrganization();

    $this->actingAs($owner)
        ->post(route('sales.enablement.assets.store'), ['type' => 'battlecard', 'title' => 'vs Competitor X'])
        ->assertRedirect();

    expect(SalesAsset::withoutGlobalScope('tenant')->where('type', 'battlecard')->where('title', 'vs Competitor X')->exists())->toBeTrue();
});

it('scores an ABM account by aggregating its company contacts', function () {
    [$org] = salesOrganization();
    app(CurrentOrganization::class)->set($org);

    $company = Company::create(['name' => 'Acme']);
    Contact::create(['first_name' => 'A', 'email' => 'a@acme.com', 'company_id' => $company->id, 'lead_score' => 40]);
    Contact::create(['first_name' => 'B', 'email' => 'b@acme.com', 'company_id' => $company->id, 'lead_score' => 30]);
    $account = TargetAccount::create(['company_id' => $company->id, 'tier' => 1]);

    $service = app(AccountService::class);
    $score = $service->score($account);
    $committee = $service->buyingCommittee($account);
    app(CurrentOrganization::class)->forget();

    expect($score)->toBe(70)
        ->and($account->refresh()->account_score)->toBe(70)
        ->and($committee)->toHaveCount(2);
});

it('lets a viewer read sales but not manage scoring', function () {
    [$org] = salesOrganization();
    $viewer = addMember($org, Role::Viewer);

    $this->actingAs($viewer)->get(route('sales.scoring.index'))->assertOk();
    $this->actingAs($viewer)
        ->post(route('sales.scoring.store'), ['name' => 'X', 'category' => 'behavioral', 'attribute' => 'email_opt_in', 'operator' => 'is_true', 'points' => 5])
        ->assertForbidden();
});

it('blocks the sales module without the plan feature', function () {
    [, $owner] = makeOrganization(); // Growth trial: no `sales`

    $this->actingAs($owner)->get(route('sales.dashboard'))->assertForbidden();
});

it('allows the sales module on Professional', function () {
    [, $owner] = salesOrganization();

    $this->actingAs($owner)->get(route('sales.dashboard'))->assertOk();
});

it('isolates sales data across tenants', function () {
    [, $ownerA] = salesOrganization('Tenant A');
    [$orgB] = salesOrganization('Tenant B');

    app(CurrentOrganization::class)->set($orgB);
    $ruleB = ScoringRule::create(['name' => 'B rule', 'category' => 'behavioral', 'attribute' => 'email_opt_in', 'operator' => 'is_true', 'points' => 5, 'is_active' => true]);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($ownerA)->delete(route('sales.scoring.destroy', $ruleB->id))->assertNotFound();
    expect(ScoringRule::withoutGlobalScope('tenant')->whereKey($ruleB->id)->exists())->toBeTrue();
});
