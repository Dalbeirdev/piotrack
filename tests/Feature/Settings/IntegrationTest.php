<?php

use App\Authorization\Role;
use App\Jobs\RunIntegrationSync;
use App\Models\AuditLog;
use App\Models\Integration;
use App\Models\SyncRun;
use App\Services\IntegrationService;
use App\Support\CurrentOrganization;
use Illuminate\Support\Facades\Queue;

it('connects, syncs, records history, and disconnects a connector end to end', function () {
    [$org, $owner] = makeOrganization();

    // Connect the demo connector with an API key.
    $this->actingAs($owner)
        ->post(route('integrations.connect'), ['provider' => 'demo_source', 'api_key' => 'secret-key'])
        ->assertRedirect();

    $integration = Integration::withoutGlobalScope('tenant')->firstWhere('provider', 'demo_source');
    expect($integration)->not->toBeNull()
        ->and($integration->organization_id)->toBe($org->id)
        ->and($integration->status)->toBe('connected');

    // Credentials are stored encrypted (never as plain text on the row).
    expect($integration->getRawOriginal('credentials'))->not->toContain('secret-key');
    expect($integration->credentials['api_key'])->toBe('secret-key');

    // Sync produces a successful run with the demo record count.
    $this->actingAs($owner)
        ->post(route('integrations.sync', $integration->id))
        ->assertRedirect();

    $run = SyncRun::withoutGlobalScope('tenant')->firstWhere('integration_id', $integration->id);
    expect($run->status)->toBe('success')->and($run->records)->toBe(25);
    expect($integration->refresh()->last_synced_at)->not->toBeNull();

    // Disconnect clears status + credentials.
    $this->actingAs($owner)
        ->post(route('integrations.disconnect', $integration->id))
        ->assertRedirect();
    $integration->refresh();
    expect($integration->status)->toBe('disconnected')->and($integration->credentials)->toBeNull();

    expect(AuditLog::withoutGlobalScope('tenant')->whereIn('action', [
        'integration.connected', 'integration.synced', 'integration.disconnected',
    ])->count())->toBe(3);
});

it('records a failed sync and recovers via reconnect', function () {
    [, $owner] = makeOrganization();

    $this->actingAs($owner)->post(route('integrations.connect'), [
        'provider' => 'demo_source',
        'api_key' => IntegrationService::FAIL_SENTINEL,
    ])->assertRedirect();

    $integration = Integration::withoutGlobalScope('tenant')->firstWhere('provider', 'demo_source');

    $this->actingAs($owner)->post(route('integrations.sync', $integration->id))->assertRedirect();

    $run = SyncRun::withoutGlobalScope('tenant')->firstWhere('integration_id', $integration->id);
    expect($run->status)->toBe('failed')->and($run->error)->not->toBeNull();
    $integration->refresh();
    expect($integration->status)->toBe('error')->and($integration->last_error)->not->toBeNull();

    // Reconnect returns it to connected so a fresh sync can be attempted.
    $this->actingAs($owner)->post(route('integrations.reconnect', $integration->id))->assertRedirect();
    expect($integration->refresh()->status)->toBe('connected');

    expect(AuditLog::withoutGlobalScope('tenant')->where('action', 'integration.sync_failed')->exists())->toBeTrue();
});

it('renders the connector catalog on the index page', function () {
    [, $owner] = makeOrganization();

    $this->actingAs($owner)->get(route('integrations.index'))->assertOk()
        ->assertInertia(fn ($p) => $p
            ->component('settings/integrations')
            ->has('connectors')
            ->where('connectors', fn ($rows) => collect($rows)->contains('key', 'demo_source')));
});

it('rejects connecting a connector that is not yet connectable', function () {
    [, $owner] = makeOrganization();

    $this->actingAs($owner)
        ->post(route('integrations.connect'), ['provider' => 'slack', 'api_key' => 'x'])
        ->assertSessionHasErrors('provider');
});

it('requires an api key for api-key connectors', function () {
    [, $owner] = makeOrganization();

    $this->actingAs($owner)
        ->post(route('integrations.connect'), ['provider' => 'demo_source'])
        ->assertSessionHasErrors('api_key');
});

it('lets a viewer read integrations but not manage them', function () {
    [$org] = makeOrganization();
    $viewer = addMember($org, Role::Viewer);

    // View is allowed.
    $this->actingAs($viewer)->get(route('integrations.index'))->assertOk();

    // Managing is forbidden.
    $this->actingAs($viewer)
        ->post(route('integrations.connect'), ['provider' => 'demo_source', 'api_key' => 'k'])
        ->assertForbidden();
});

it('forbids the index to a member without the view permission', function () {
    [$org] = makeOrganization();
    $billing = addMember($org, Role::BillingAdministrator);

    $this->actingAs($billing)->get(route('integrations.index'))->assertForbidden();
});

it('isolates integrations across tenants', function () {
    [$orgA, $ownerA] = makeOrganization('A');
    [$orgB] = makeOrganization('B');

    app(CurrentOrganization::class)->set($orgB);
    $integrationB = Integration::create(['provider' => 'demo_source', 'name' => 'Demo', 'status' => 'connected', 'credentials' => ['api_key' => 'k']]);
    app(CurrentOrganization::class)->forget();

    // Owner of A cannot act on B's integration (tenant-scoped route binding).
    $this->actingAs($ownerA)->post(route('integrations.sync', $integrationB->id))->assertNotFound();
});

it('runs a sync from the queued job under the correct tenant', function () {
    Queue::fake();
    [$org, $owner] = makeOrganization();

    $this->actingAs($owner)->post(route('integrations.connect'), ['provider' => 'demo_source', 'api_key' => 'k'])->assertRedirect();
    $integration = Integration::withoutGlobalScope('tenant')->firstWhere('provider', 'demo_source');

    // Dispatching is wired to the queue.
    $this->actingAs($owner)->post(route('integrations.queue-sync', $integration->id))->assertRedirect();
    Queue::assertPushed(RunIntegrationSync::class);

    // Executing the job records a successful run under org context.
    app(CurrentOrganization::class)->forget();
    (new RunIntegrationSync($integration->id, $org->id))->handle(app(IntegrationService::class), app(CurrentOrganization::class));

    expect(SyncRun::withoutGlobalScope('tenant')->where('integration_id', $integration->id)->where('status', 'success')->exists())->toBeTrue();
});
