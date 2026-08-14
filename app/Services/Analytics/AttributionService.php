<?php

namespace App\Services\Analytics;

use App\Models\AdMetric;
use App\Models\Contact;
use App\Models\Deal;
use Illuminate\Support\Facades\DB;

/**
 * Revenue attribution (ATTR). First/last/linear multi-touch across a contact's
 * touchpoints (acquisition source + logged activities), plus channel/campaign/
 * owner revenue rollups of won deals, CAC and marketing ROI. All figures come
 * from real tenant rows; money is minor units.
 */
class AttributionService
{
    /**
     * Chronological touchpoints for a contact: the acquisition source (first) then
     * each logged activity, keyed by channel.
     *
     * @return list<array{channel: string, at: string}>
     */
    public function touchpoints(Contact $contact): array
    {
        $touches = [];

        $source = $contact->lead_source ?: 'direct';
        $touches[] = ['channel' => $source, 'at' => (string) ($contact->created_at?->toIso8601String() ?? '')];

        $activities = DB::table('activities')
            ->where('subject_type', 'contact')
            ->where('subject_id', $contact->id)
            ->orderByRaw('COALESCE(occurred_at, created_at)')
            ->get(['type', 'occurred_at', 'created_at']);

        foreach ($activities as $a) {
            $touches[] = [
                'channel' => (string) $a->type,
                'at' => (string) ($a->occurred_at ?? $a->created_at ?? ''),
            ];
        }

        return $touches;
    }

    public function firstTouch(Contact $contact): string
    {
        return $this->touchpoints($contact)[0]['channel'];
    }

    public function lastTouch(Contact $contact): string
    {
        $touches = $this->touchpoints($contact);

        return $touches[count($touches) - 1]['channel'];
    }

    /**
     * Linear multi-touch: split one unit of credit equally across all touchpoints,
     * summed per channel (values total 1.0).
     *
     * @return array<string, float>
     */
    public function multiTouch(Contact $contact): array
    {
        $touches = $this->touchpoints($contact);
        $count = count($touches);
        if ($count === 0) {
            return [];
        }

        $credit = 1 / $count;
        $out = [];
        foreach ($touches as $t) {
            $out[$t['channel']] = round(($out[$t['channel']] ?? 0) + $credit, 4);
        }

        return $out;
    }

    /**
     * Won-deal revenue grouped by acquisition channel (minor units).
     *
     * @return array<string, int>
     */
    public function channelRevenue(): array
    {
        return $this->wonRevenueBy('lead_source');
    }

    /**
     * Won-deal revenue grouped by campaign (minor units).
     *
     * @return array<string, int>
     */
    public function campaignRevenue(): array
    {
        return $this->wonRevenueBy('campaign');
    }

    /**
     * Won-deal revenue grouped by sales owner (minor units).
     *
     * @return array<int|string, int>
     */
    public function salesAttribution(): array
    {
        return Deal::whereHas('stage', fn ($q) => $q->where('is_won', true))
            ->selectRaw('owner_id, COALESCE(SUM(value),0) AS revenue')
            ->groupBy('owner_id')
            ->pluck('revenue', 'owner_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * Customer acquisition cost = total ad spend / customers won (minor units).
     */
    public function cac(): int
    {
        $customers = Deal::whereHas('stage', fn ($q) => $q->where('is_won', true))->count();
        if ($customers === 0) {
            return 0;
        }

        $spend = (int) AdMetric::sum('spend');

        return (int) round($spend / $customers);
    }

    /**
     * Marketing ROI = won revenue / ad spend (ratio, divisor-guarded).
     */
    public function marketingRoi(): float
    {
        $spend = (int) AdMetric::sum('spend');
        if ($spend === 0) {
            return 0.0;
        }

        $revenue = (int) Deal::whereHas('stage', fn ($q) => $q->where('is_won', true))->sum('value');

        return round($revenue / $spend, 2);
    }

    /**
     * @return array<string, int>
     */
    private function wonRevenueBy(string $column): array
    {
        return Deal::whereHas('stage', fn ($q) => $q->where('is_won', true))
            ->selectRaw("COALESCE(NULLIF($column, ''), 'unattributed') AS bucket, COALESCE(SUM(value),0) AS revenue")
            ->groupBy('bucket')
            ->pluck('revenue', 'bucket')
            ->map(fn ($v) => (int) $v)
            ->all();
    }
}
