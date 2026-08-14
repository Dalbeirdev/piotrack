<?php

use App\Authorization\Role;
use App\Models\ContentPiece;
use App\Models\OutreachCampaign;
use App\Services\Content\OutreachService;
use App\Support\CurrentOrganization;

it('runs the outreach pipeline and records a placement', function () {
    [$org, $owner] = makeOrganization();
    app(CurrentOrganization::class)->set($org);
    $campaign = OutreachCampaign::create(['name' => 'PR Q3', 'type' => 'digital_pr']);
    $prospect = $campaign->prospects()->create(['name' => 'TechCrunch', 'domain' => 'techcrunch.com', 'status' => 'identified']);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($owner)->post(route('content.outreach.prospects.status', $prospect->id), ['status' => 'contacted'])->assertRedirect();
    expect($prospect->refresh()->status)->toBe('contacted');

    $this->actingAs($owner)->post(route('content.outreach.prospects.placement', $prospect->id), [
        'placement_url' => 'https://techcrunch.com/story', 'domain_authority' => 92, 'anchor_text' => 'managed IT', 'link_type' => 'dofollow',
    ])->assertRedirect();

    $prospect->refresh();
    expect($prospect->status)->toBe('won')
        ->and($prospect->placement_url)->toBe('https://techcrunch.com/story')
        ->and($prospect->hasPlacement())->toBeTrue();
});

it('rolls up an outreach campaign', function () {
    [$org] = makeOrganization();
    app(CurrentOrganization::class)->set($org);
    $campaign = OutreachCampaign::create(['name' => 'Links', 'type' => 'link_building']);
    $campaign->prospects()->create(['name' => 'A', 'status' => 'identified']);
    $campaign->prospects()->create(['name' => 'B', 'status' => 'won', 'placement_url' => 'https://b.com/x']);
    $rollup = app(OutreachService::class)->rollup($campaign);
    app(CurrentOrganization::class)->forget();

    expect($rollup['total'])->toBe(2)->and($rollup['placements'])->toBe(1);
});

it('lets a viewer read content but not manage it', function () {
    [$org] = makeOrganization();
    $viewer = addMember($org, Role::Viewer);

    $this->actingAs($viewer)->get(route('content.pieces.index'))->assertOk();
    $this->actingAs($viewer)->post(route('content.pieces.store'), ['title' => 'X', 'content_type' => 'article'])->assertForbidden();
});

it('blocks content when the plan does not include it', function () {
    [$org, $owner] = makeOrganization();
    subscribeOrganization($org, 'starter'); // starter has no content feature

    $this->actingAs($owner)->get(route('content.dashboard'))->assertForbidden();
});

it('allows content on a plan that includes it', function () {
    [, $owner] = makeOrganization(); // Growth trial includes content

    $this->actingAs($owner)->get(route('content.dashboard'))->assertOk();
});

it('isolates content pieces across tenants', function () {
    [, $ownerA] = makeOrganization('A');
    [$orgB] = makeOrganization('B');

    app(CurrentOrganization::class)->set($orgB);
    $pieceB = ContentPiece::create(['title' => 'Secret', 'slug' => 'secret-b', 'content_type' => 'article']);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($ownerA)->get(route('content.pieces.show', $pieceB->id))->assertNotFound();
});
