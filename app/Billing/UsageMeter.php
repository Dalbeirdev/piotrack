<?php

namespace App\Billing;

use App\Models\Organization;
use App\Models\UsageCounter;
use Illuminate\Support\Carbon;

/**
 * Usage metering & reporting (ENTL-005/006/007). Two kinds of meter:
 *  - live meters computed from current state (e.g. members = active members +
 *    pending invitations, i.e. seats consumed),
 *  - counter meters accumulated in usage_counters for the current period
 *    (emails, API calls, … as their modules land).
 */
class UsageMeter
{
    public function __construct(private Entitlements $entitlements) {}

    public function usage(Organization $organization, Limit|string $key): int
    {
        $key = $key instanceof Limit ? $key->value : $key;

        return match ($key) {
            Limit::Members->value => $this->memberSeatsUsed($organization),
            default => $this->counterUsage($organization, $key),
        };
    }

    /**
     * Remaining allowance (null = unlimited).
     */
    public function remaining(Organization $organization, Limit|string $key): ?int
    {
        $limit = $this->entitlements->limit($organization, $key);

        if ($limit === null) {
            return null;
        }

        return max(0, $limit - $this->usage($organization, $key));
    }

    /**
     * Whether $additional more units stay within the limit.
     */
    public function withinLimit(Organization $organization, Limit|string $key, int $additional = 1): bool
    {
        $limit = $this->entitlements->limit($organization, $key);

        if ($limit === null) {
            return true; // unlimited
        }

        return $this->usage($organization, $key) + $additional <= $limit;
    }

    public function increment(Organization $organization, Limit|string $key, int $by = 1): void
    {
        $key = $key instanceof Limit ? $key->value : $key;
        [$start, $end] = $this->currentPeriod($organization);

        $counter = UsageCounter::firstOrCreate(
            ['organization_id' => $organization->id, 'key' => $key, 'period_start' => $start],
            ['period_end' => $end, 'used' => 0],
        );

        $counter->increment('used', $by);
    }

    /**
     * A display-ready summary for the billing portal: every limit the plan
     * defines, with used / allowance / remaining.
     *
     * @return list<array{key: string, used: int, limit: int|null, remaining: int|null}>
     */
    public function summary(Organization $organization): array
    {
        $rows = [];

        foreach ($this->entitlements->limits($organization) as $key => $limit) {
            $used = $this->usage($organization, $key);
            $rows[] = [
                'key' => $key,
                'used' => $used,
                'limit' => $limit,
                'remaining' => $limit === null ? null : max(0, $limit - $used),
            ];
        }

        return $rows;
    }

    private function memberSeatsUsed(Organization $organization): int
    {
        $members = $organization->members()->wherePivot('status', 'active')->count();
        $pending = $organization->invitations()->pending()->count();

        return $members + $pending;
    }

    private function counterUsage(Organization $organization, string $key): int
    {
        [$start] = $this->currentPeriod($organization);

        return (int) UsageCounter::query()
            ->where('organization_id', $organization->id)
            ->where('key', $key)
            ->where('period_start', $start)
            ->value('used');
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function currentPeriod(Organization $organization): array
    {
        $subscription = $organization->activeSubscription();

        if ($subscription === null) {
            return [now()->startOfMonth(), now()->endOfMonth()];
        }

        return [
            $subscription->current_period_start ?? now()->startOfMonth(),
            $subscription->current_period_end ?? now()->endOfMonth(),
        ];
    }
}
