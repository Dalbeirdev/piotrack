<?php

use App\Authorization\Role;
use App\Models\AdCampaign;
use App\Support\CurrentOrganization;

it('lets a viewer read ads but not manage them', function () {
    [$org] = adsOrganization();
    $viewer = addMember($org, Role::Viewer);

    $this->actingAs($viewer)->get(route('ads.campaigns.index'))->assertOk();
    $this->actingAs($viewer)->post(route('ads.campaigns.store'), [
        'name' => 'X', 'platform' => 'meta', 'objective' => 'leads', 'daily_budget' => 100,
    ])->assertForbidden();
});

it('forbids retargeting management to a viewer', function () {
    [$org] = adsOrganization();
    $viewer = addMember($org, Role::Viewer);

    $this->actingAs($viewer)->post(route('ads.retargeting.store'), ['name' => 'R', 'source' => 'all_contacts'])->assertForbidden();
});

it('blocks advertising when the plan does not include it', function () {
    // Growth trial has no `advertising` feature.
    [$org, $owner] = makeOrganization();

    $this->actingAs($owner)->get(route('ads.dashboard'))->assertForbidden();
});

it('allows advertising on a plan that includes it', function () {
    [, $owner] = adsOrganization();

    $this->actingAs($owner)->get(route('ads.dashboard'))->assertOk();
});

it('isolates campaigns across tenants', function () {
    [, $ownerA] = adsOrganization('A');
    [$orgB] = adsOrganization('B');

    app(CurrentOrganization::class)->set($orgB);
    $campaignB = AdCampaign::create(['platform' => 'meta', 'name' => 'B', 'objective' => 'leads', 'daily_budget' => 1000]);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($ownerA)->get(route('ads.campaigns.show', $campaignB->id))->assertNotFound();
});
