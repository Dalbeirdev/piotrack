<?php

namespace App\Console\Commands;

use App\Jobs\RefreshAdMetrics;
use App\Models\AdCampaign;
use Illuminate\Console\Command;

/**
 * Queues a metrics refresh for every active ad campaign (PPC/LIAD/META).
 * Runs across all tenants; each dispatched job carries its own organization id.
 */
class RefreshAdMetricsCommand extends Command
{
    protected $signature = 'ads:refresh-metrics';

    protected $description = 'Queue metrics refresh for active ad campaigns';

    public function handle(): int
    {
        $queued = 0;

        AdCampaign::query()
            ->where('status', 'active')
            ->each(function (AdCampaign $campaign) use (&$queued) {
                RefreshAdMetrics::dispatch($campaign->id, $campaign->organization_id);
                $queued++;
            });

        $this->components->info("Queued {$queued} ad-metrics refresh job(s).");

        return self::SUCCESS;
    }
}
