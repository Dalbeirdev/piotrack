<?php

namespace App\Jobs;

use App\Models\Integration;
use App\Models\Organization;
use App\Services\IntegrationService;
use App\Support\CurrentOrganization;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Runs a connector sync off the request cycle (INTG-003). Because BelongsToTenant
 * scopes every query to the current organization, the job re-establishes tenant
 * context from the integration's own organization_id before touching the DB —
 * a queued worker has no request and therefore no ambient tenant.
 */
class RunIntegrationSync implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $integrationId, public int $organizationId) {}

    public function handle(IntegrationService $integrations, CurrentOrganization $current): void
    {
        $organization = Organization::find($this->organizationId);

        if ($organization === null) {
            return;
        }

        $current->set($organization);

        $integration = Integration::find($this->integrationId);

        if ($integration === null) {
            return;
        }

        $integrations->sync($integration);
    }
}
