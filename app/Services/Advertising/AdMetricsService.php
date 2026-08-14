<?php

namespace App\Services\Advertising;

use App\Advertising\AdKpi;
use App\Advertising\AdProviderManager;
use App\Models\AdCampaign;
use App\Models\AdMetric;
use App\Support\AuditLogger;
use Illuminate\Support\Carbon;

/**
 * Pulls daily performance via the AdProvider (fixture tested) and rolls it up
 * into KPIs. Metric writes are idempotent per (campaign, date), so a re-run
 * updates rather than duplicates.
 */
class AdMetricsService
{
    public function __construct(
        private AdProviderManager $providers,
        private AuditLogger $audit,
    ) {}

    public function refresh(AdCampaign $campaign, ?int $days = null): int
    {
        $days ??= (int) config('advertising.metrics_window', 30);
        $rows = $this->providers->for($campaign)->metrics($campaign, $days);

        foreach ($rows as $row) {
            if ($row['date'] === '') {
                continue;
            }

            AdMetric::updateOrCreate(
                ['ad_campaign_id' => $campaign->id, 'date' => Carbon::parse($row['date'])->startOfDay()],
                [
                    'organization_id' => $campaign->organization_id,
                    'impressions' => $row['impressions'],
                    'clicks' => $row['clicks'],
                    'spend' => $row['spend'],
                    'conversions' => $row['conversions'],
                    'revenue' => $row['revenue'],
                ],
            );
        }

        $this->audit->log('ads.metrics.refreshed', context: ['campaign' => $campaign->name, 'days' => $days], resourceType: 'ad_campaign', resourceId: (string) $campaign->id, organizationId: $campaign->organization_id);

        return count($rows);
    }

    public function campaignKpi(AdCampaign $campaign, ?Carbon $since = null): AdKpi
    {
        $metrics = $campaign->metrics()
            ->when($since, fn ($q) => $q->where('date', '>=', $since))
            ->get();

        return AdKpi::from(
            (int) $metrics->sum('impressions'),
            (int) $metrics->sum('clicks'),
            (int) $metrics->sum('spend'),
            (int) $metrics->sum('conversions'),
            (int) $metrics->sum('revenue'),
        );
    }

    /**
     * KPI rollup across all of the organization's campaigns.
     */
    public function organizationKpi(?Carbon $since = null): AdKpi
    {
        $metrics = AdMetric::query()
            ->when($since, fn ($q) => $q->where('date', '>=', $since))
            ->get();

        return AdKpi::from(
            (int) $metrics->sum('impressions'),
            (int) $metrics->sum('clicks'),
            (int) $metrics->sum('spend'),
            (int) $metrics->sum('conversions'),
            (int) $metrics->sum('revenue'),
        );
    }
}
