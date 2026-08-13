<?php

use App\Models\Contact;
use App\Models\Funnel;
use App\Models\MarketingList;
use App\Services\Marketing\FunnelService;
use App\Services\Marketing\ListService;
use App\Support\CurrentOrganization;

it('adds and removes contacts and maintains the member count', function () {
    [$org] = makeOrganization();
    app(CurrentOrganization::class)->set($org);

    $list = MarketingList::create(['name' => 'L', 'type' => 'static']);
    $contact = Contact::create(['first_name' => 'M', 'email' => 'm@example.com']);
    $svc = app(ListService::class);

    $svc->addContact($list, $contact);
    $svc->addContact($list, $contact); // idempotent
    expect($list->refresh()->member_count)->toBe(1);

    $svc->removeContact($list, $contact);
    expect($list->refresh()->member_count)->toBe(0);
    app(CurrentOrganization::class)->forget();
});

it('resolves dynamic list members from criteria', function () {
    [$org] = makeOrganization();
    app(CurrentOrganization::class)->set($org);

    Contact::create(['first_name' => 'Hot', 'email' => 'hot@example.com', 'lead_score' => 80]);
    Contact::create(['first_name' => 'Cold', 'email' => 'cold@example.com', 'lead_score' => 5]);
    $list = MarketingList::create(['name' => 'Hot leads', 'type' => 'dynamic', 'criteria' => ['min_lead_score' => 50]]);

    $members = app(ListService::class)->members($list);
    expect($members)->toHaveCount(1)->and($members->first()->email)->toBe('hot@example.com');
    app(CurrentOrganization::class)->forget();
});

it('isolates lists across tenants', function () {
    [$orgA, $ownerA] = makeOrganization('A');
    [$orgB] = makeOrganization('B');

    app(CurrentOrganization::class)->set($orgB);
    $listB = MarketingList::create(['name' => 'Secret', 'type' => 'static']);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($ownerA)->get(route('marketing.lists.show', $listB->id))->assertNotFound();
});

it('counts contacts per funnel stage by lifecycle', function () {
    [$org] = makeOrganization();
    app(CurrentOrganization::class)->set($org);

    Contact::create(['first_name' => 'A', 'email' => 'a@example.com', 'lifecycle_stage' => 'lead']);
    Contact::create(['first_name' => 'B', 'email' => 'b@example.com', 'lifecycle_stage' => 'lead']);
    Contact::create(['first_name' => 'C', 'email' => 'c@example.com', 'lifecycle_stage' => 'mql']);

    $funnel = Funnel::create(['name' => 'Main']);
    $funnel->stages()->create(['name' => 'Leads', 'position' => 1, 'category' => 'tof', 'lifecycle_stage' => 'lead']);
    $funnel->stages()->create(['name' => 'MQLs', 'position' => 2, 'category' => 'mof', 'lifecycle_stage' => 'mql']);

    $counts = app(FunnelService::class)->stageCounts($funnel);
    expect($counts[0]['count'])->toBe(2)->and($counts[1]['count'])->toBe(1);
    app(CurrentOrganization::class)->forget();
});
