<?php

namespace App\Console\Commands;

use App\Jobs\SendCampaignJob;
use App\Models\Campaign;
use Illuminate\Console\Command;

/**
 * Queues sends for campaigns whose scheduled time has arrived (EMAIL-001).
 * Runs across all tenants; each dispatched job carries its own organization id.
 */
class SendScheduledCampaigns extends Command
{
    protected $signature = 'marketing:send-scheduled-campaigns';

    protected $description = 'Queue sends for due scheduled campaigns';

    public function handle(): int
    {
        $queued = 0;

        Campaign::query()
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->each(function (Campaign $campaign) use (&$queued) {
                SendCampaignJob::dispatch($campaign->id, $campaign->organization_id);
                $queued++;
            });

        $this->components->info("Queued {$queued} scheduled campaign(s).");

        return self::SUCCESS;
    }
}
