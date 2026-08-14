<?php

use App\Models\FeatureFlag;
use App\Services\Platform\FeatureFlagService;

it('treats an unknown flag as off', function () {
    [$org] = deliveryOrganization();

    expect(app(FeatureFlagService::class)->enabled('nope', $org))->toBeFalse();
});

it('honours the flag default when there is no targeting', function () {
    [$org] = deliveryOrganization();
    FeatureFlag::create(['key' => 'beta.reports', 'is_enabled' => true]);

    expect(app(FeatureFlagService::class)->enabled('beta.reports', $org))->toBeTrue();
});

it('enables a flag for an explicitly targeted organization', function () {
    [$orgA] = deliveryOrganization('A');
    [$orgB] = deliveryOrganization('B');

    FeatureFlag::create([
        'key' => 'beta.portal',
        'is_enabled' => false,
        'rollout' => ['organizations' => [$orgA->id]],
    ]);

    $flags = app(FeatureFlagService::class);
    expect($flags->enabled('beta.portal', $orgA))->toBeTrue()
        ->and($flags->enabled('beta.portal', $orgB))->toBeFalse();
});

it('lets a kill switch override every other rule', function () {
    [$org] = deliveryOrganization();

    FeatureFlag::create([
        'key' => 'risky.feature',
        'is_enabled' => true,
        'is_kill_switch' => true,
        // Even an explicitly targeted org must be switched off.
        'rollout' => ['organizations' => [$org->id], 'percentage' => 100],
    ]);

    expect(app(FeatureFlagService::class)->enabled('risky.feature', $org))->toBeFalse();
});

it('rolls out by percentage deterministically', function () {
    [$org] = deliveryOrganization();
    FeatureFlag::create(['key' => 'staged', 'is_enabled' => false, 'rollout' => ['percentage' => 100]]);

    $flags = app(FeatureFlagService::class);
    $first = $flags->enabled('staged', $org);

    expect($first)->toBeTrue()
        // Same org + flag must resolve identically every time.
        ->and($flags->enabled('staged', $org))->toBe($first)
        ->and($flags->enabled('staged', $org))->toBe($first);

    FeatureFlag::where('key', 'staged')->update(['rollout' => json_encode(['percentage' => 0])]);
    expect($flags->enabled('staged', $org))->toBeFalse();
});

it('saves a flag through the platform console', function () {
    $staff = platformStaff();

    $this->actingAs($staff)->post(route('platform.flags.save'), [
        'key' => 'new.flag',
        'description' => 'A staged rollout',
        'is_enabled' => true,
        'rollout' => ['percentage' => 25],
    ])->assertRedirect();

    expect(FeatureFlag::where('key', 'new.flag')->first()->rollout)->toBe(['percentage' => 25]);
});
