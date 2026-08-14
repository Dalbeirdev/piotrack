<?php

use App\Models\Contact;
use App\Models\MarketingList;
use App\Models\RetargetingAudience;
use App\Services\Advertising\RetargetingService;
use App\Services\Marketing\ListService;
use App\Support\CurrentOrganization;

it('builds a list audience excluding converted customers', function () {
    [$org] = adsOrganization();
    app(CurrentOrganization::class)->set($org);

    $list = MarketingList::create(['name' => 'Leads', 'type' => 'static']);
    $lead = Contact::create(['first_name' => 'Lead', 'email' => 'lead@x.com', 'lifecycle_stage' => 'lead']);
    $customer = Contact::create(['first_name' => 'Cust', 'email' => 'cust@x.com', 'lifecycle_stage' => 'customer']);
    $svc = app(ListService::class);
    $svc->addContact($list, $lead);
    $svc->addContact($list, $customer);

    $audience = RetargetingAudience::create([
        'name' => 'Remarketing', 'source' => 'list', 'marketing_list_id' => $list->id, 'exclude_converted' => true,
    ]);
    $count = app(RetargetingService::class)->rebuild($audience);
    app(CurrentOrganization::class)->forget();

    expect($count)->toBe(1)                       // customer excluded
        ->and($audience->refresh()->member_count)->toBe(1);
});

it('includes converted contacts when exclusion is off', function () {
    [$org] = adsOrganization();
    app(CurrentOrganization::class)->set($org);

    $list = MarketingList::create(['name' => 'All', 'type' => 'static']);
    $svc = app(ListService::class);
    $svc->addContact($list, Contact::create(['first_name' => 'A', 'email' => 'a@x.com', 'lifecycle_stage' => 'lead']));
    $svc->addContact($list, Contact::create(['first_name' => 'B', 'email' => 'b@x.com', 'lifecycle_stage' => 'customer']));

    $audience = RetargetingAudience::create(['name' => 'R', 'source' => 'list', 'marketing_list_id' => $list->id, 'exclude_converted' => false]);
    $count = app(RetargetingService::class)->rebuild($audience);
    app(CurrentOrganization::class)->forget();

    expect($count)->toBe(2);
});

it('builds a funnel-stage audience', function () {
    [$org] = adsOrganization();
    app(CurrentOrganization::class)->set($org);

    Contact::create(['first_name' => 'M1', 'email' => 'm1@x.com', 'lifecycle_stage' => 'mql']);
    Contact::create(['first_name' => 'M2', 'email' => 'm2@x.com', 'lifecycle_stage' => 'mql']);
    Contact::create(['first_name' => 'L', 'email' => 'l@x.com', 'lifecycle_stage' => 'lead']);

    $audience = RetargetingAudience::create(['name' => 'MQLs', 'source' => 'funnel_stage', 'rules' => ['lifecycle_stage' => 'mql'], 'exclude_converted' => true]);
    $count = app(RetargetingService::class)->rebuild($audience);
    app(CurrentOrganization::class)->forget();

    expect($count)->toBe(2);
});

it('produces a hashed-email sync payload', function () {
    [$org] = adsOrganization();
    app(CurrentOrganization::class)->set($org);

    $list = MarketingList::create(['name' => 'L', 'type' => 'static']);
    app(ListService::class)->addContact($list, Contact::create(['first_name' => 'A', 'email' => 'Person@Example.com', 'lifecycle_stage' => 'lead']));
    $audience = RetargetingAudience::create(['name' => 'R', 'source' => 'list', 'marketing_list_id' => $list->id]);

    $payload = app(RetargetingService::class)->syncPayload($audience);
    app(CurrentOrganization::class)->forget();

    expect($payload)->toHaveCount(1)
        ->and($payload[0])->toBe(hash('sha256', 'person@example.com')); // normalized + hashed
});

it('creates a retargeting audience via the controller and counts members', function () {
    [$org, $owner] = adsOrganization();
    app(CurrentOrganization::class)->set($org);
    $list = MarketingList::create(['name' => 'Newsletter', 'type' => 'static']);
    app(ListService::class)->addContact($list, Contact::create(['first_name' => 'A', 'email' => 'a@x.com', 'lifecycle_stage' => 'lead']));
    app(CurrentOrganization::class)->forget();

    $this->actingAs($owner)->post(route('ads.retargeting.store'), [
        'name' => 'List RT', 'source' => 'list', 'marketing_list_id' => $list->id, 'exclude_converted' => true,
    ])->assertRedirect();

    $audience = RetargetingAudience::withoutGlobalScope('tenant')->firstWhere('name', 'List RT');
    expect($audience)->not->toBeNull()->and($audience->member_count)->toBe(1);
});
