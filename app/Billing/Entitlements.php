<?php

namespace App\Billing;

use App\Models\Organization;

/**
 * Central entitlement resolution (ENTL-001/003): Plan → Entitlements → Limits →
 * (active) Subscription → Feature access, with a restrictive free fallback when
 * an organization has no active subscription.
 *
 * Semantics:
 *  - features default-DENY (a premium feature is off unless a plan grants it),
 *  - limits default to uncapped when a plan doesn't list them; a listed limit
 *    with a null value is explicitly unlimited.
 *
 * Resolved maps are memoized per organization for the request.
 */
class Entitlements
{
    /** @var array<int, array{features: array<string,bool>, limits: array<string, int|null>}> */
    private array $cache = [];

    public function feature(Organization $organization, Feature|string $feature): bool
    {
        $key = $feature instanceof Feature ? $feature->value : $feature;

        return $this->resolve($organization)['features'][$key] ?? false;
    }

    /**
     * The numeric allowance for a limit key. null = unlimited/uncapped.
     */
    public function limit(Organization $organization, Limit|string $limit): ?int
    {
        $key = $limit instanceof Limit ? $limit->value : $limit;
        $limits = $this->resolve($organization)['limits'];

        return array_key_exists($key, $limits) ? $limits[$key] : null;
    }

    /**
     * @return array<string, bool>
     */
    public function features(Organization $organization): array
    {
        return $this->resolve($organization)['features'];
    }

    /**
     * @return array<string, int|null>
     */
    public function limits(Organization $organization): array
    {
        return $this->resolve($organization)['limits'];
    }

    public function forget(Organization $organization): void
    {
        unset($this->cache[$organization->id]);
    }

    /**
     * @return array{features: array<string,bool>, limits: array<string, int|null>}
     */
    private function resolve(Organization $organization): array
    {
        return $this->cache[$organization->id] ??= $this->build($organization);
    }

    /**
     * @return array{features: array<string,bool>, limits: array<string, int|null>}
     */
    private function build(Organization $organization): array
    {
        $subscription = $organization->activeSubscription();

        if ($subscription === null) {
            $fallback = PlanCatalog::freeFallback();
            $features = [];
            foreach ($fallback['features'] as $key) {
                $features[$key] = true;
            }

            return ['features' => $features, 'limits' => $fallback['limits']];
        }

        $features = [];
        $limits = [];

        foreach ($subscription->plan->entitlements as $entitlement) {
            if ($entitlement->kind === 'feature') {
                $features[$entitlement->key] = (bool) $entitlement->bool_value;
            } else {
                $limits[$entitlement->key] = $entitlement->int_value; // null = unlimited
            }
        }

        return ['features' => $features, 'limits' => $limits];
    }
}
