<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Integrations\ConnectorRegistry;
use App\Jobs\RunIntegrationSync;
use App\Models\Integration;
use App\Models\SyncRun;
use App\Services\IntegrationService;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Connector management UI + actions (INTG). The catalog merges the code registry
 * with the org's stored connectors; connect/disconnect/reconnect/sync all flow
 * through {@see IntegrationService} so audit + health stay consistent. Syncs run
 * synchronously here for immediate feedback, and also expose a queued path.
 */
class IntegrationController extends Controller
{
    public function __construct(
        private IntegrationService $integrations,
        private CurrentOrganization $currentOrganization,
    ) {}

    public function index(): Response
    {
        return Inertia::render('settings/integrations', [
            'connectors' => $this->integrations->catalog(),
            'recentRuns' => SyncRun::with('integration:id,provider,name')
                ->latest('id')
                ->limit(15)
                ->get()
                ->map(fn (SyncRun $run) => [
                    'id' => $run->id,
                    'provider' => $run->integration?->provider,
                    'provider_name' => $run->integration?->name,
                    'status' => $run->status,
                    'records' => $run->records,
                    'error' => $run->error,
                    'started_at' => $run->started_at?->toIso8601String(),
                    'finished_at' => $run->finished_at?->toIso8601String(),
                ])
                ->all(),
        ]);
    }

    public function connect(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'provider' => ['required', 'string', Rule::in(array_column(ConnectorRegistry::all(), 'key'))],
            'api_key' => ['nullable', 'string', 'max:500'],
        ]);

        $credentials = isset($data['api_key']) && $data['api_key'] !== ''
            ? ['api_key' => $data['api_key']]
            : [];

        $this->integrations->connect($data['provider'], $credentials);

        return back()->with('status', __(':name connected.', ['name' => ConnectorRegistry::name($data['provider'])]));
    }

    public function disconnect(Integration $integration): RedirectResponse
    {
        $this->integrations->disconnect($integration);

        return back()->with('status', __(':name disconnected.', ['name' => $integration->name]));
    }

    public function reconnect(Integration $integration): RedirectResponse
    {
        $this->integrations->reconnect($integration);

        return back()->with('status', __(':name reconnected.', ['name' => $integration->name]));
    }

    public function sync(Integration $integration): RedirectResponse
    {
        $run = $this->integrations->sync($integration);

        return back()->with('status', $run->status === 'success'
            ? __('Synced :n records from :name.', ['n' => $run->records, 'name' => $integration->name])
            : __('Sync failed: :error', ['error' => $run->error]));
    }

    /**
     * Queue a background sync (INTG-003) — proves the async path without blocking.
     */
    public function queueSync(Integration $integration): RedirectResponse
    {
        RunIntegrationSync::dispatch($integration->id, $this->currentOrganization->id());

        return back()->with('status', __('Sync queued for :name.', ['name' => $integration->name]));
    }
}
