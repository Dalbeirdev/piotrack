<?php

namespace App\Jobs;

use App\Models\AdCampaign;
use App\Models\Organization;
use App\Services\Advertising\AdMetricsService;
use App\Support\CurrentOrganization;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Pulls fresh ad metrics for a campaign off the request cycle (ADR-0006).
 * Re-establishes tenant context from the campaign's organization; the metric
 * upsert is idempotent per (campaign, date), so a retry does not duplicate.
 */
class RefreshAdMetrics implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $campaignId, public int $organizationId) {}

    public function handle(AdMetricsService $metrics, CurrentOrganization $current): void
    {
        $organization = Organization::find($this->organizationId);

        if ($organization === null) {
            return;
        }

        $current->set($organization);

        $campaign = AdCampaign::find($this->campaignId);

        if ($campaign === null) {
            return;
        }

        $metrics->refresh($campaign);
    }
}
