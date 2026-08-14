<?php

use App\Authorization\Role;
use App\Models\AuditLog;
use App\Models\ImpersonationSession;
use App\Models\Organization;
use App\Models\User;
use App\Services\Platform\ImpersonationService;
use Illuminate\Support\Facades\Auth;

/**
 * Impersonation is the most dangerous capability in the product: it hands staff
 * a customer's account. These tests exist to prove the guard rails hold.
 *
 * @return array{0: Organization, 1: User}
 */
function deliveryOrganization(string $name = 'Test Org'): array
{
    [$org, $owner] = makeOrganization($name);
    subscribeOrganization($org, 'professional');

    return [$org, $owner];
}

function platformStaff(Role $role = Role::PlatformSuperAdmin): User
{
    return User::factory()->create(['platform_role' => $role->value]);
}

it('records an audited session with the reason when starting', function () {
    [, $target] = deliveryOrganization();
    $staff = platformStaff();

    $session = app(ImpersonationService::class)->start($staff, $target, 'Investigating a billing complaint');

    expect($session->impersonator_id)->toBe($staff->id)
        ->and($session->user_id)->toBe($target->id)
        ->and($session->reason)->toBe('Investigating a billing complaint')
        ->and($session->ended_at)->toBeNull()
        ->and(Auth::id())->toBe($target->id)  // now acting as the target
        ->and(AuditLog::where('action', 'admin.impersonation.started')->exists())->toBeTrue();
});

it('never lets platform staff be impersonated', function () {
    $staff = platformStaff();
    $otherStaff = platformStaff(Role::PlatformSupportAdmin);

    expect(fn () => app(ImpersonationService::class)->start($staff, $otherStaff, 'escalating my own access'))
        ->toThrow(RuntimeException::class, 'Platform staff cannot be impersonated');

    expect(ImpersonationSession::count())->toBe(0);
});

it('refuses impersonation by a non-platform user', function () {
    [, $ownerA] = deliveryOrganization('A');
    [, $ownerB] = deliveryOrganization('B');

    expect(fn () => app(ImpersonationService::class)->start($ownerA, $ownerB, 'curiosity'))
        ->toThrow(RuntimeException::class, 'Only platform staff');
});

it('requires a reason', function () {
    [, $target] = deliveryOrganization();
    $staff = platformStaff();

    expect(fn () => app(ImpersonationService::class)->start($staff, $target, '   '))
        ->toThrow(RuntimeException::class, 'A reason is required');
});

it('ends the session and restores the original user on stop', function () {
    [, $target] = deliveryOrganization();
    $staff = platformStaff();
    $service = app(ImpersonationService::class);

    $session = $service->start($staff, $target, 'Reproducing a reported bug');
    expect($service->isImpersonating())->toBeTrue();

    $service->stop();

    expect($session->refresh()->ended_at)->not->toBeNull()
        ->and($service->isImpersonating())->toBeFalse()
        ->and(Auth::id())->toBe($staff->id)   // back to the operator
        ->and(AuditLog::where('action', 'admin.impersonation.stopped')->exists())->toBeTrue();
});

it('surfaces the active session to the UI so it is never invisible', function () {
    [, $target] = deliveryOrganization();
    $staff = platformStaff();

    $this->actingAs($staff)
        ->post(route('platform.impersonate.start', $target->id), ['reason' => 'Checking a support report'])
        ->assertRedirect();

    // The next page render carries the banner state.
    $this->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('impersonation.active', true)->where('impersonation.user', $target->name));
});

it('refuses the impersonation route without the permission', function () {
    [$org, $target] = deliveryOrganization();
    $member = addMember($org, Role::MarketingManager);

    $this->actingAs($member)
        ->post(route('platform.impersonate.start', $target->id), ['reason' => 'I would like a look'])
        ->assertForbidden();

    expect(ImpersonationSession::count())->toBe(0);
});

it('lets an impersonated session always be stopped without a permission', function () {
    [, $target] = deliveryOrganization();
    $staff = platformStaff();

    $this->actingAs($staff)
        ->post(route('platform.impersonate.start', $target->id), ['reason' => 'Support investigation'])
        ->assertRedirect();

    // Stopping is deliberately ungated so nobody gets trapped inside a session.
    $this->post(route('impersonate.stop'))->assertRedirect();

    expect(ImpersonationSession::first()->ended_at)->not->toBeNull();
});

it('keeps the platform console out of reach of tenant users', function () {
    [$org] = deliveryOrganization();
    $manager = addMember($org, Role::MarketingManager);

    $this->actingAs($manager)->get(route('platform.dashboard'))->assertForbidden();
    $this->actingAs(platformStaff())->get(route('platform.dashboard'))->assertOk();
});
