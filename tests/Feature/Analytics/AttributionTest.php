<?php

use App\Models\Activity;
use App\Models\AdMetric;
use App\Models\Contact;
use App\Models\Deal;
use App\Services\Analytics\AttributionService;
use App\Support\CurrentOrganization;

it('attributes first and last touch across a contact journey', function () {
    [$org] = analyticsOrganization();
    app(CurrentOrganization::class)->set($org);

    $contact = Contact::create(['first_name' => 'A', 'email' => 'a@x.com', 'lead_source' => 'organic']);
    Activity::create(['subject_type' => 'contact', 'subject_id' => $contact->id, 'type' => 'email', 'title' => 'Nurture', 'occurred_at' => now()->addMinute()]);
    Activity::create(['subject_type' => 'contact', 'subject_id' => $contact->id, 'type' => 'meeting', 'title' => 'Demo', 'occurred_at' => now()->addMinutes(2)]);

    $service = app(AttributionService::class);

    expect($service->firstTouch($contact))->toBe('organic')
        ->and($service->lastTouch($contact))->toBe('meeting');
    app(CurrentOrganization::class)->forget();
});

it('splits multi-touch credit evenly and totals one', function () {
    [$org] = analyticsOrganization();
    app(CurrentOrganization::class)->set($org);

    $contact = Contact::create(['first_name' => 'B', 'email' => 'b@x.com', 'lead_source' => 'paid']);
    Activity::create(['subject_type' => 'contact', 'subject_id' => $contact->id, 'type' => 'email', 'title' => 'Touch', 'occurred_at' => now()]);
    Activity::create(['subject_type' => 'contact', 'subject_id' => $contact->id, 'type' => 'call', 'title' => 'Touch', 'occurred_at' => now()]);

    $credit = app(AttributionService::class)->multiTouch($contact);
    app(CurrentOrganization::class)->forget();

    expect(round(array_sum($credit), 2))->toBe(1.0)
        ->and($credit['paid'])->toBeGreaterThan(0.3);
});

it('defaults an unsourced contact to direct', function () {
    [$org] = analyticsOrganization();
    app(CurrentOrganization::class)->set($org);
    $contact = Contact::create(['first_name' => 'C', 'email' => 'c@x.com']);

    expect(app(AttributionService::class)->firstTouch($contact))->toBe('direct');
    app(CurrentOrganization::class)->forget();
});

it('rolls up won revenue by channel and campaign', function () {
    [$org] = analyticsOrganization();
    app(CurrentOrganization::class)->set($org);

    $a = Deal::factory()->create(['organization_id' => $org->id, 'value' => 300000, 'lead_source' => 'organic', 'campaign' => 'spring']);
    placeDealInStage($a, 'is_won');
    $b = Deal::factory()->create(['organization_id' => $org->id, 'value' => 200000, 'lead_source' => 'organic', 'campaign' => 'spring']);
    placeDealInStage($b, 'is_won');
    Deal::factory()->create(['organization_id' => $org->id, 'value' => 999999, 'lead_source' => 'paid']); // open, excluded

    $service = app(AttributionService::class);
    $channels = $service->channelRevenue();
    $campaigns = $service->campaignRevenue();
    app(CurrentOrganization::class)->forget();

    expect($channels['organic'])->toBe(500000)
        ->and($channels)->not->toHaveKey('paid')
        ->and($campaigns['spring'])->toBe(500000);
});

it('computes CAC and marketing ROI against ad spend', function () {
    [$org] = analyticsOrganization();
    app(CurrentOrganization::class)->set($org);

    $campaign = analyticsAdCampaign($org);
    AdMetric::create(['ad_campaign_id' => $campaign->id, 'date' => now()->toDateString(), 'impressions' => 100, 'clicks' => 10, 'spend' => 100000, 'conversions' => 2, 'revenue' => 0]);

    $won = Deal::factory()->create(['organization_id' => $org->id, 'value' => 400000]);
    placeDealInStage($won, 'is_won');

    $service = app(AttributionService::class);
    $cac = $service->cac();
    $roi = $service->marketingRoi();
    app(CurrentOrganization::class)->forget();

    expect($cac)->toBe(100000)   // 100000 spend / 1 customer
        ->and($roi)->toBe(4.0);  // 400000 revenue / 100000 spend
});

it('guards CAC and ROI when there is no spend or no customers', function () {
    [$org] = analyticsOrganization();
    app(CurrentOrganization::class)->set($org);
    $service = app(AttributionService::class);

    expect($service->cac())->toBe(0)->and($service->marketingRoi())->toBe(0.0);
    app(CurrentOrganization::class)->forget();
});
