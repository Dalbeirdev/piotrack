<?php

use App\Authorization\Role;
use App\Billing\Entitlements;
use App\Billing\Feature;
use App\Billing\Limit;
use App\Services\SubscriptionService;

it('resolves plan features and limits', function () {
    [$org] = makeOrganization();
    subscribeOrganization($org, 'professional');
    $ent = app(Entitlements::class);

    expect($ent->feature($org, Feature::AiVisibility))->toBeTrue()
        ->and($ent->feature($org, Feature::Api))->toBeTrue()
        ->and($ent->feature($org, Feature::WhiteLabel))->toBeFalse()
        ->and($ent->limit($org, Limit::Members))->toBe(25);
});

it('falls back to the restrictive free tier with no active subscription', function () {
    [$org] = makeOrganization();
    // Cancel the trial immediately → no active subscription.
    app(SubscriptionService::class)->cancel($org->activeSubscription(), immediately: true);
    app(Entitlements::class)->forget($org);

    $ent = app(Entitlements::class);
    expect($ent->feature($org, Feature::Teams))->toBeFalse()
        ->and($ent->limit($org, Limit::Members))->toBe(1);
});

it('gates the teams feature by entitlement on the backend', function () {
    [$org, $owner] = makeOrganization();
    subscribeOrganization($org, 'starter'); // no teams feature

    $this->actingAs($owner)->get(route('teams.index'))->assertForbidden();

    // Upgrading unlocks it.
    subscribeOrganization($org, 'growth'); // has teams
    $this->actingAs($owner)->get(route('teams.index'))->assertOk();
});

it('gates the audit log by entitlement', function () {
    [$org, $owner] = makeOrganization();
    subscribeOrganization($org, 'starter'); // no audit_log feature

    $this->actingAs($owner)->get(route('audit.index'))->assertForbidden();
});

it('shares plan feature entitlements to the frontend', function () {
    [$org, $owner] = makeOrganization();
    subscribeOrganization($org, 'professional');

    $this->actingAs($owner)->get(route('billing.index'))->assertInertia(fn ($page) => $page
        ->where('entitlements.plan', 'professional')
        ->where('entitlements.features', fn ($features) => ($features['ai_visibility'] ?? false) === true),
    );
});

it('applies entitlement gating even to a platform super admin (plan capability, not a permission)', function () {
    [$org] = makeOrganization();
    subscribeOrganization($org, 'starter'); // no teams
    $admin = addMember($org, Role::Viewer);
    $admin->forceFill(['platform_role' => Role::PlatformSuperAdmin->value])->save();

    // The RBAC Gate bypass grants permissions, not plan features. Teams stays
    // blocked on Starter regardless of who is asking.
    $this->actingAs($admin->refresh())->get(route('teams.index'))->assertForbidden();
});
