<?php

namespace App\Console\Commands;

use App\Billing\Entitlements;
use App\Billing\Feature;
use App\Models\Organization;
use App\Services\Ai\AiVisibilityDashboard;
use App\Support\CurrentOrganization;
use Illuminate\Console\Command;

/**
 * Runs each entitled tenant's monitored prompt library across the AI engines
 * (AIVIS-001…006). Idempotent per prompt/engine/day, so a re-run the same day
 * records nothing new.
 */
class RunAiVisibilityChecks extends Command
{
    protected $signature = 'ai:run-visibility-checks';

    protected $description = 'Run the AI-visibility prompt library for every entitled organization';

    public function handle(
        AiVisibilityDashboard $dashboard,
        CurrentOrganization $current,
        Entitlements $entitlements,
    ): int {
        $checks = 0;

        Organization::query()->each(function (Organization $organization) use ($dashboard, $current, $entitlements, &$checks) {
            if (! $entitlements->feature($organization, Feature::AiVisibility)) {
                return;
            }

            $current->set($organization);
            $checks += $dashboard->runLibrary($organization->name);
        });

        $current->forget();

        $this->components->info("Recorded {$checks} AI-visibility check(s).");

        return self::SUCCESS;
    }
}
