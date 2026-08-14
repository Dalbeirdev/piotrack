<?php

use App\Models\AdCampaign;
use App\Models\AdMetric;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Keyword;
use App\Models\Organization;
use App\Models\User;
use App\Services\Analytics\AnalyticsService;
use App\Support\CurrentOrganization;

/**
 * Create an organization on a plan that includes the `analytics` feature.
 *
 * @return array{0: Organization, 1: User}
 */
function analyticsOrganization(string $name = 'Test Org'): array
{
    [$org, $owner] = makeOrganization($name);
    subscribeOrganization($org, 'professional'); // Professional includes `analytics`

    return [$org, $owner];
}

/** Move a deal into a won (or lost) stage of its own pipeline. */
function placeDealInStage(Deal $deal, string $flag): void
{
    $stage = $deal->pipeline->stages()->where($flag, true)->first()
        ?? $deal->pipeline->stages()->create(['name' => ucfirst(str_replace('is_', '', $flag)), 'sort_order' => 99, $flag => true]);

    $deal->forceFill(['stage_id' => $stage->id])->save();
}

/** An ad campaign for metric rollups. */
function analyticsAdCampaign(Organization $org): AdCampaign
{
    return AdCampaign::create([
        'organization_id' => $org->id,
        'platform' => 'google_search',
        'name' => 'QA campaign',
        'status' => 'active',
    ]);
}

it('computes the acquisition funnel from real pipeline data', function () {
    [$org] = analyticsOrganization();
    app(CurrentOrganization::class)->set($org);

    Contact::create(['first_name' => 'A', 'email' => 'a@x.com', 'lifecycle_stage' => 'lead']);
    Contact::create(['first_name' => 'B', 'email' => 'b@x.com', 'lifecycle_stage' => 'mql']);
    Contact::create(['first_name' => 'C', 'email' => 'c@x.com', 'lifecycle_stage' => 'sql']);

    $won = Deal::factory()->create(['organization_id' => $org->id, 'value' => 500000]);
    placeDealInStage($won, 'is_won');
    Deal::factory()->create(['organization_id' => $org->id, 'value' => 250000]); // open

    $funnel = app(AnalyticsService::class)->funnel();
    app(CurrentOrganization::class)->forget();

    expect($funnel['leads'])->toBe(3)
        ->and($funnel['mqls'])->toBe(1)
        ->and($funnel['sqls'])->toBe(1)
        ->and($funnel['closed_won'])->toBe(1)
        ->and($funnel['qualified_pipeline'])->toBe(250000); // only the open deal
});

it('rolls up advertising KPIs with guarded divisors', function () {
    [$org] = analyticsOrganization();
    app(CurrentOrganization::class)->set($org);

    $campaign = analyticsAdCampaign($org);
    AdMetric::create(['ad_campaign_id' => $campaign->id, 'date' => now()->toDateString(), 'impressions' => 1000, 'clicks' => 100, 'spend' => 20000, 'conversions' => 10, 'revenue' => 100000]);

    $kpi = app(AnalyticsService::class)->advertising()->toArray();
    app(CurrentOrganization::class)->forget();

    expect($kpi['ctr'])->toBe(10.0)      // 100/1000
        ->and($kpi['cpc'])->toBe(200)     // 20000/100 minor units
        ->and($kpi['roas'])->toBe(5.0);   // 100000/20000
});

it('returns zeroed advertising KPIs when there is no spend', function () {
    [$org] = analyticsOrganization();
    app(CurrentOrganization::class)->set($org);
    $kpi = app(AnalyticsService::class)->advertising()->toArray();
    app(CurrentOrganization::class)->forget();

    expect($kpi['roas'])->toBe(0.0)->and($kpi['cpc'])->toBe(0)->and($kpi['impressions'])->toBe(0);
});

it('summarizes SEO visibility from tracked keywords', function () {
    [$org] = analyticsOrganization();
    app(CurrentOrganization::class)->set($org);

    Keyword::create(['phrase' => 'msp support', 'is_tracked' => true, 'current_position' => 2]);
    Keyword::create(['phrase' => 'it services', 'is_tracked' => true, 'current_position' => 8]);
    Keyword::create(['phrase' => 'cloud backup', 'is_tracked' => true, 'current_position' => 40]);

    $seo = app(AnalyticsService::class)->seo();
    app(CurrentOrganization::class)->forget();

    expect($seo['tracked_keywords'])->toBe(3)
        ->and($seo['top_three'])->toBe(1)
        ->and($seo['page_one'])->toBe(2);
});

it('sums recurring revenue from won deals only', function () {
    [$org] = analyticsOrganization();
    app(CurrentOrganization::class)->set($org);

    $won = Deal::factory()->create(['organization_id' => $org->id, 'mrr' => 50000, 'arr' => 600000, 'value' => 600000]);
    placeDealInStage($won, 'is_won');
    Deal::factory()->create(['organization_id' => $org->id, 'mrr' => 99999, 'arr' => 999999]); // open, excluded

    $revenue = app(AnalyticsService::class)->revenue();
    app(CurrentOrganization::class)->forget();

    expect($revenue['mrr'])->toBe(50000)->and($revenue['arr'])->toBe(600000);
});

it('renders the analytics dashboard for an entitled organization', function () {
    [, $owner] = analyticsOrganization();

    $this->actingAs($owner)->get(route('analytics.dashboard'))->assertOk();
});
