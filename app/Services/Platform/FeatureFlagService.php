<?php

namespace App\Services\Platform;

use App\Models\FeatureFlag;
use App\Models\Organization;
use App\Support\AuditLogger;

/**
 * Feature flags with staged rollout and kill switches (ADMIN-004).
 *
 * Resolution order is deliberate:
 *  1. a kill switch that is ON disables the feature for everyone, immediately,
 *     regardless of any targeting — that is the point of a kill switch;
 *  2. an explicitly targeted organization gets the feature;
 *  3. otherwise a percentage rollout decides, deterministically per organization
 *     so a tenant does not flip between requests;
 *  4. otherwise the flag's default.
 */
class FeatureFlagService
{
    public function __construct(private AuditLogger $audit) {}

    public function enabled(string $key, ?Organization $organization = null): bool
    {
        $flag = FeatureFlag::where('key', $key)->first();

        if ($flag === null) {
            return false; // unknown flags are off
        }

        // A kill switch that is engaged wins over everything else.
        if ($flag->is_kill_switch && $flag->is_enabled) {
            return false;
        }

        $rollout = $flag->rollout ?? [];

        if ($organization !== null) {
            /** @var list<int> $targeted */
            $targeted = $rollout['organizations'] ?? [];
            if (in_array($organization->id, array_map('intval', $targeted), true)) {
                return true;
            }

            $percentage = (int) ($rollout['percentage'] ?? 0);
            if ($percentage > 0) {
                return $this->bucket($key, $organization->id) < $percentage;
            }
        }

        return $flag->is_enabled;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function upsert(string $key, array $attributes): FeatureFlag
    {
        $flag = FeatureFlag::updateOrCreate(['key' => $key], $attributes);

        $this->audit->log('admin.feature_flag.updated', context: [
            'key' => $key,
            'is_enabled' => $flag->is_enabled,
            'is_kill_switch' => $flag->is_kill_switch,
        ], resourceType: 'feature_flag', resourceId: (string) $flag->id);

        return $flag;
    }

    /**
     * Stable 0–99 bucket for a (flag, organization) pair.
     */
    private function bucket(string $key, int $organizationId): int
    {
        return (int) (crc32($key.':'.$organizationId) % 100);
    }
}
