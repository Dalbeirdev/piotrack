<?php

use App\Authorization\Role;
use App\Models\Campaign;
use App\Models\MarketingList;
use App\Support\CurrentOrganization;

it('lets a viewer read marketing but not manage it', function () {
    [$org] = makeOrganization();
    $viewer = addMember($org, Role::Viewer);

    $this->actingAs($viewer)->get(route('marketing.lists.index'))->assertOk();
    $this->actingAs($viewer)->post(route('marketing.lists.store'), ['name' => 'X', 'type' => 'static'])->assertForbidden();
});

it('separates campaign drafting from sending', function () {
    [$org] = makeOrganization();
    $user = addMember($org, Role::MarketingUser); // manage but not send

    app(CurrentOrganization::class)->set($org);
    $list = MarketingList::create(['name' => 'L', 'type' => 'static']);
    $campaign = Campaign::create(['name' => 'Draft', 'channel' => 'email', 'subject' => 'S', 'body_html' => 'x', 'marketing_list_id' => $list->id]);
    app(CurrentOrganization::class)->forget();

    // Can create a campaign…
    $this->actingAs($user)->post(route('marketing.campaigns.store'), ['name' => 'New', 'channel' => 'email'])->assertRedirect();
    // …but cannot send one.
    $this->actingAs($user)->post(route('marketing.campaigns.send', $campaign->id))->assertForbidden();
});

it('blocks marketing when the plan does not include the feature', function () {
    [$org, $owner] = makeOrganization();
    subscribeOrganization($org, 'starter'); // starter has no marketing feature

    $this->actingAs($owner)->get(route('marketing.lists.index'))->assertForbidden();
});

it('allows marketing on a plan that includes it', function () {
    [, $owner] = makeOrganization(); // Growth trial includes marketing

    $this->actingAs($owner)->get(route('marketing.dashboard'))->assertOk();
});

it('isolates campaigns across tenants', function () {
    [, $ownerA] = makeOrganization('A');
    [$orgB] = makeOrganization('B');

    app(CurrentOrganization::class)->set($orgB);
    $campaignB = Campaign::create(['name' => 'B', 'channel' => 'email', 'subject' => 'S', 'body_html' => 'x']);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($ownerA)->get(route('marketing.campaigns.show', $campaignB->id))->assertNotFound();
});
