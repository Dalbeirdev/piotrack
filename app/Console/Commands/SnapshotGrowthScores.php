<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Services\Analytics\GrowthScoreService;
use App\Support\CurrentOrganization;
use Illuminate\Console\Command;

/**
 * Persists a daily MSP Growth Score snapshot per tenant (GSCORE-013 trend
 * tracking). Each organization's context is established in turn so the score
 * services read only that tenant's data. Idempotent: the snapshot is keyed by
 * organization + date, so re-running the same day updates rather than duplicates.
 */
class SnapshotGrowthScores extends Command
{
    protected $signature = 'analytics:snapshot-growth-scores';

    protected $description = 'Compute and store today\'s MSP Growth Score for every organization';

    public function handle(GrowthScoreService $scores, CurrentOrganization $current): int
    {
        $count = 0;

        Organization::query()->each(function (Organization $organization) use ($scores, $current, &$count) {
            $current->set($organization);
            $scores->snapshot();
            $count++;
        });

        $current->forget();

        $this->components->info("Stored {$count} growth-score snapshot(s).");

        return self::SUCCESS;
    }
}
