<?php

use App\Models\AdCampaign;
use App\Models\AdGroup;
use App\Models\AdKeyword;
use App\Models\AdMetric;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use App\Services\Advertising\AdMetricsService;
use App\Support\CurrentOrganization;

/**
 * @return array{0: Organization, 1: User}
 */
function adsOrganization(string $name = 'Test Org'): array
{
    [$org, $owner] = makeOrganization($name);
    subscribeOrganization($org, 'professional'); // Professional includes `advertising`

    return [$org, $owner];
}

function adCampaign(Organization $org, array $overrides = []): AdCampaign
{
    app(CurrentOrganization::class)->set($org);
    $campaign = AdCampaign::create(array_merge([
        'platform' => 'google_search', 'name' => 'Campaign', 'objective' => 'leads', 'daily_budget' => 5000,
    ], $overrides));
    app(CurrentOrganization::class)->forget();

    return $campaign;
}

it('creates a campaign and audits it', function () {
    [$org, $owner] = adsOrganization();

    $this->actingAs($owner)->post(route('ads.campaigns.store'), [
        'name' => 'Dallas Search', 'platform' => 'google_search', 'objective' => 'leads', 'daily_budget' => 5000,
    ])->assertRedirect();

    $campaign = AdCampaign::withoutGlobalScope('tenant')->firstWhere('name', 'Dallas Search');
    expect($campaign)->not->toBeNull()->and($campaign->organization_id)->toBe($org->id);
    expect(AuditLog::withoutGlobalScope('tenant')->where('action', 'ads.campaign.created')->exists())->toBeTrue();
});

it('requires an ad group with an ad before activating', function () {
    [$org, $owner] = adsOrganization();
    $campaign = adCampaign($org, ['status' => 'draft']);

    // No ad group yet → activation blocked.
    $this->actingAs($owner)->post(route('ads.campaigns.status', $campaign->id), ['status' => 'active'])->assertSessionHasErrors('status');
    expect($campaign->refresh()->status)->toBe('draft');

    app(CurrentOrganization::class)->set($org);
    $group = $campaign->groups()->create(['name' => 'Group 1']);
    $group->ads()->create(['name' => 'Ad 1']);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($owner)->post(route('ads.campaigns.status', $campaign->id), ['status' => 'active'])->assertRedirect();
    expect($campaign->refresh()->status)->toBe('active');
});

it('manages ad groups, ads and negative keywords', function () {
    [$org, $owner] = adsOrganization();
    $campaign = adCampaign($org);

    $this->actingAs($owner)->post(route('ads.groups.store', $campaign->id), ['name' => 'Group 1', 'bid_strategy' => 'manual_cpc', 'bid_amount' => 200])->assertRedirect();
    $group = AdGroup::withoutGlobalScope('tenant')->firstWhere('ad_campaign_id', $campaign->id);

    $this->actingAs($owner)->post(route('ads.ads.store', $group->id), ['name' => 'Ad A', 'headline' => 'Managed IT'])->assertRedirect();
    $this->actingAs($owner)->post(route('ads.keywords.store', $group->id), ['phrase' => 'managed it services', 'match_type' => 'exact', 'is_negative' => false])->assertRedirect();
    $this->actingAs($owner)->post(route('ads.keywords.store', $group->id), ['phrase' => 'free', 'match_type' => 'broad', 'is_negative' => true])->assertRedirect();

    expect(AdKeyword::withoutGlobalScope('tenant')->where('ad_group_id', $group->id)->count())->toBe(2);
    expect(AdKeyword::withoutGlobalScope('tenant')->where('is_negative', true)->count())->toBe(1);
    expect($group->ads()->withoutGlobalScope('tenant')->count())->toBe(1);
});

it('refreshes metrics idempotently and rolls up KPIs', function () {
    [$org] = adsOrganization();
    $campaign = adCampaign($org, ['daily_budget' => 10000]);

    app(CurrentOrganization::class)->set($org);
    $service = app(AdMetricsService::class);
    $service->refresh($campaign, 7);
    $service->refresh($campaign, 7); // idempotent per (campaign, date)
    $kpi = $service->campaignKpi($campaign);
    app(CurrentOrganization::class)->forget();

    expect(AdMetric::withoutGlobalScope('tenant')->where('ad_campaign_id', $campaign->id)->count())->toBe(7);
    expect($kpi->impressions)->toBeGreaterThan(0)
        ->and($kpi->clicks)->toBeGreaterThan(0);
});

it('refreshes metrics from the controller', function () {
    [$org, $owner] = adsOrganization();
    $campaign = adCampaign($org);

    $this->actingAs($owner)->post(route('ads.campaigns.refresh-metrics', $campaign->id))->assertRedirect();

    expect(AdMetric::withoutGlobalScope('tenant')->where('ad_campaign_id', $campaign->id)->exists())->toBeTrue();
    expect(AuditLog::withoutGlobalScope('tenant')->where('action', 'ads.metrics.refreshed')->exists())->toBeTrue();
});
