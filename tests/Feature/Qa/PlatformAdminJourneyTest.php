<?php

declare(strict_types=1);

/**
 * QA §52 - the platform-admin journey, with the emphasis §52 places on it:
 * "Confirm sensitive operations are audited."
 *
 * The subtle risk here is not whether platform actions are audited - they are -
 * but WHERE. A platform-level action (editing a global feature flag) has no
 * tenant, yet AuditLogger defaults an unspecified organization_id to the
 * current organization. A platform admin who also belongs to a tenant org
 * therefore stamps a global action with that tenant, and the tenant's own audit
 * viewer - which filters on organization_id - would surface a platform-internal
 * operation to a customer. That is the case this pins.
 */

use App\Authorization\Role;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use App\Services\Platform\PlatformAdminService;
use App\Support\CurrentOrganization;

beforeEach(function () {
    // Three tenants, so platform aggregates have something to count.
    [$this->acme, $this->acmeOwner] = makeOrganization('Acme Managed IT Services');
    subscribeOrganization($this->acme, 'professional');
    [$this->northstar] = makeOrganization('Northstar Cybersecurity');
    subscribeOrganization($this->northstar, 'enterprise');

    app(CurrentOrganization::class)->forget();
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

it('shows a platform admin every tenant, not only their own', function () {
    $staff = User::factory()->create(['platform_role' => Role::PlatformSuperAdmin->value]);

    $overview = app(PlatformAdminService::class)->overview();

    // Organization, Subscription and User are global models, so the counts must
    // span the whole platform even though the caller sits in no tenant.
    expect($overview['organizations'])->toBe(Organization::count())
        ->and($overview['organizations'])->toBeGreaterThanOrEqual(2)
        ->and($overview['active_subscriptions'])->toBeGreaterThanOrEqual(2);

    $tenants = app(PlatformAdminService::class)->tenants();
    $names = collect($tenants)->pluck('name');

    expect($names)->toContain('Acme Managed IT Services')
        ->and($names)->toContain('Northstar Cybersecurity');
});

it('excludes platform staff from the impersonatable member lists', function () {
    // A super admin who is also seated in a tenant must not be offered as an
    // impersonation target of that tenant.
    $this->acme->members()->attach(
        User::factory()->create(['platform_role' => Role::PlatformSuperAdmin->value])->id,
        ['role' => Role::Admin->value, 'status' => 'active', 'joined_at' => now()],
    );

    $tenants = app(PlatformAdminService::class)->tenants();
    $acme = collect($tenants)->firstWhere('name', 'Acme Managed IT Services');

    $emails = collect($acme['users'])->pluck('email');

    // The tenant owner is listed; the platform staffer seated in the org is not.
    expect($emails)->toContain($this->acmeOwner->email)
        ->and(collect($acme['users'])->count())->toBe(1);
});

it('audits a feature-flag change without leaking it into a tenant audit log', function () {
    // A platform admin who also owns a tenant. current_organization_id resolves
    // to that org for the duration of the request.
    $staff = $this->acmeOwner;
    $staff->forceFill(['platform_role' => Role::PlatformSuperAdmin->value])->save();

    $this->actingAs($staff)->post(route('platform.flags.save'), [
        'key' => 'new_dashboard',
        'description' => 'Staged rollout of the new dashboard',
        'is_enabled' => true,
    ])->assertRedirect();

    // The action must be audited at all.
    $entry = AuditLog::where('action', 'admin.feature_flag.updated')->first();
    expect($entry)->not->toBeNull('the flag change was not audited');

    // And it must NOT surface in the tenant's own audit viewer, which filters
    // on organization_id. A platform action carrying a tenant id would show a
    // customer evidence of platform-internal operations.
    app(CurrentOrganization::class)->set($this->acme);

    $response = $this->actingAs($this->acmeOwner)->get(route('audit.index'));
    $response->assertSuccessful();

    expect($response->getContent())->not->toContain('admin.feature_flag.updated');
});

it('keeps the platform console out of reach of a tenant admin', function () {
    // §52's console must be platform-only, checked at the backend.
    $tenantAdmin = addMember($this->acme, Role::Admin);

    $this->actingAs($tenantAdmin)->get(route('platform.dashboard'))->assertForbidden();
    $this->actingAs($tenantAdmin)->get(route('platform.flags'))->assertForbidden();
    $this->actingAs($tenantAdmin)->post(route('platform.flags.save'), [
        'key' => 'sneaky', 'is_enabled' => true,
    ])->assertForbidden();
});
