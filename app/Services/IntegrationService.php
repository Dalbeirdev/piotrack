<?php

namespace App\Services;

use App\Integrations\ConnectorRegistry;
use App\Models\Integration;
use App\Models\SyncRun;
use App\Support\AuditLogger;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Business rules for third-party connectors (INTG-002/003). The service owns
 * the connect/disconnect/reconnect/sync lifecycle and derives health from the
 * outcome of syncs. Credentials are stored encrypted on the model; nothing here
 * ever writes a secret into an audit context (Master Prompt §38 honesty: only
 * the demo/api_key connectors actually sync — OAuth connectors are Planned).
 *
 * The demo connector's sync forces a failure when its API key equals the
 * sentinel below, which lets the failure path (status → error, failed run,
 * last_error recorded) be proven end to end without external services.
 */
class IntegrationService
{
    /** Sentinel credential that makes the demo connector's sync fail. */
    public const FAIL_SENTINEL = '__fail__';

    public function __construct(private AuditLogger $audit) {}

    /**
     * Connect (or re-key) a connector for the current organization.
     *
     * @param  array<string, mixed>  $credentials
     * @param  array<string, mixed>  $settings
     */
    public function connect(string $provider, array $credentials, array $settings = []): Integration
    {
        $connector = ConnectorRegistry::find($provider);

        if ($connector === null) {
            throw ValidationException::withMessages([
                'provider' => __('Unknown connector.'),
            ]);
        }

        if (! $connector['connectable']) {
            throw ValidationException::withMessages([
                'provider' => __(':name is not available for connection yet.', ['name' => $connector['name']]),
            ]);
        }

        if ($connector['auth_type'] === 'api_key' && empty($credentials['api_key'])) {
            throw ValidationException::withMessages([
                'api_key' => __('An API key is required to connect :name.', ['name' => $connector['name']]),
            ]);
        }

        $integration = Integration::updateOrCreate(
            ['provider' => $provider],
            [
                'name' => $connector['name'],
                'status' => 'connected',
                'credentials' => $credentials,
                'settings' => $settings,
                'last_error' => null,
            ],
        );

        $this->audit->log(
            'integration.connected',
            context: ['provider' => $provider],
            resourceType: 'integration',
            resourceId: (string) $integration->id,
        );

        return $integration;
    }

    public function disconnect(Integration $integration): Integration
    {
        $integration->update([
            'status' => 'disconnected',
            'credentials' => null,
            'last_error' => null,
        ]);

        $this->audit->log(
            'integration.disconnected',
            context: ['provider' => $integration->provider],
            resourceType: 'integration',
            resourceId: (string) $integration->id,
        );

        return $integration;
    }

    /**
     * Clear an error state and return the connector to connected so a fresh
     * sync can be attempted (credentials are retained).
     */
    public function reconnect(Integration $integration): Integration
    {
        if ($integration->credentials === null) {
            throw ValidationException::withMessages([
                'provider' => __('Reconnect requires credentials — connect the integration first.'),
            ]);
        }

        $integration->update(['status' => 'connected', 'last_error' => null]);

        $this->audit->log(
            'integration.reconnected',
            context: ['provider' => $integration->provider],
            resourceType: 'integration',
            resourceId: (string) $integration->id,
        );

        return $integration;
    }

    /**
     * Run a sync for the connector, recording a SyncRun row and updating the
     * integration's health from the result. Failures are caught and surfaced as
     * an error status rather than thrown, so a scheduled/queued caller records a
     * failed run instead of crashing.
     */
    public function sync(Integration $integration): SyncRun
    {
        $run = SyncRun::create([
            'integration_id' => $integration->id,
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            if ($integration->status === 'disconnected' || $integration->credentials === null) {
                throw new RuntimeException('Integration is not connected.');
            }

            $records = $this->pull($integration);

            $run->update([
                'status' => 'success',
                'finished_at' => now(),
                'records' => $records,
            ]);

            $integration->update([
                'status' => 'connected',
                'last_synced_at' => now(),
                'last_error' => null,
            ]);

            $this->audit->log(
                'integration.synced',
                context: ['provider' => $integration->provider, 'records' => $records],
                resourceType: 'integration',
                resourceId: (string) $integration->id,
            );
        } catch (Throwable $e) {
            $run->update([
                'status' => 'failed',
                'finished_at' => now(),
                'error' => $e->getMessage(),
            ]);

            $integration->update([
                'status' => 'error',
                'last_error' => $e->getMessage(),
            ]);

            $this->audit->log(
                'integration.sync_failed',
                context: ['provider' => $integration->provider, 'error' => $e->getMessage()],
                resourceType: 'integration',
                resourceId: (string) $integration->id,
            );
        }

        return $run->refresh();
    }

    /**
     * Pull records from the upstream connector. Only the demo connector performs
     * real work in this environment; other api_key connectors verify a key is
     * present but return zero until their client implementation lands (Planned).
     */
    private function pull(Integration $integration): int
    {
        $credentials = $integration->credentials ?? [];
        $apiKey = $credentials['api_key'] ?? null;

        if ($integration->provider === 'demo_source') {
            if ($apiKey === self::FAIL_SENTINEL) {
                throw new RuntimeException('Upstream authentication failed (invalid API key).');
            }

            // Deterministic demo payload so connect → sync → history is provable.
            return (int) ($integration->settings['record_count'] ?? 25);
        }

        return 0;
    }

    /**
     * Merge the code catalog with the org's stored connectors for display.
     *
     * @return list<array<string, mixed>>
     */
    public function catalog(): array
    {
        $stored = Integration::query()->get()->keyBy('provider');

        return array_map(function (array $connector) use ($stored) {
            $integration = $stored->get($connector['key']);

            if (! $integration instanceof Integration) {
                return [
                    ...$connector,
                    'status' => 'disconnected',
                    'health' => 'disconnected',
                    'last_synced_at' => null,
                    'last_error' => null,
                    'integration_id' => null,
                ];
            }

            return [
                ...$connector,
                'status' => $integration->status,
                'health' => $integration->health(),
                'last_synced_at' => $integration->last_synced_at?->toIso8601String(),
                'last_error' => $integration->last_error,
                'integration_id' => $integration->id,
            ];
        }, ConnectorRegistry::all());
    }
}
