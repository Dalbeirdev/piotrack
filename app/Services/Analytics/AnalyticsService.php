<?php

namespace App\Services\Analytics;

use App\Advertising\AdKpi;
use App\Models\AdMetric;
use App\Models\Booking;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Keyword;

/**
 * Analytics dashboard (ANLY). A pure read/aggregation layer over the tenant's
 * own CRM pipeline, advertising, SEO and revenue data — every figure is computed
 * from real rows under the active tenant scope, never fabricated. Money is in
 * minor units; KPI ratios reuse the divisor-guarded {@see AdKpi}.
 */
class AnalyticsService
{
    /**
     * @return array<string, mixed>
     */
    public function dashboard(): array
    {
        return [
            'funnel' => $this->funnel(),
            'advertising' => $this->advertising()->toArray(),
            'seo' => $this->seo(),
            'revenue' => $this->revenue(),
            'sources' => $this->sourceBreakdown(),
        ];
    }

    /**
     * Acquisition funnel counts from contacts, bookings and deals×stage flags.
     *
     * @return array<string, int>
     */
    public function funnel(): array
    {
        $leads = Contact::count();
        $mqls = Contact::where('lifecycle_stage', 'mql')->count();
        $sqls = Contact::where('lifecycle_stage', 'sql')->count();
        $meetings = Booking::count();

        $opportunities = Deal::whereHas('stage', fn ($q) => $q->where('is_won', false)->where('is_lost', false))->count();
        $proposals = Deal::whereHas('stage', fn ($q) => $q->whereLike('name', '%roposal%'))->count();
        $won = Deal::whereHas('stage', fn ($q) => $q->where('is_won', true))->count();
        $lost = Deal::whereHas('stage', fn ($q) => $q->where('is_lost', true))->count();

        $qualifiedPipeline = (int) Deal::whereHas('stage', fn ($q) => $q->where('is_won', false)->where('is_lost', false))->sum('value');

        return [
            'leads' => $leads,
            'mqls' => $mqls,
            'sqls' => $sqls,
            'meetings' => $meetings,
            'opportunities' => $opportunities,
            'proposals' => $proposals,
            'qualified_pipeline' => $qualifiedPipeline,
            'closed_won' => $won,
            'closed_lost' => $lost,
        ];
    }

    /**
     * Rolled-up paid-media KPIs across all ad metrics.
     */
    public function advertising(): AdKpi
    {
        $row = AdMetric::query()
            ->selectRaw('COALESCE(SUM(impressions),0) i, COALESCE(SUM(clicks),0) c, COALESCE(SUM(spend),0) s, COALESCE(SUM(conversions),0) v, COALESCE(SUM(revenue),0) r')
            ->first();

        return AdKpi::from(
            impressions: (int) ($row->i ?? 0),
            clicks: (int) ($row->c ?? 0),
            spend: (int) ($row->s ?? 0),
            conversions: (int) ($row->v ?? 0),
            revenue: (int) ($row->r ?? 0),
        );
    }

    /**
     * Organic search visibility summary from tracked keywords.
     *
     * @return array<string, int>
     */
    public function seo(): array
    {
        $tracked = Keyword::where('is_tracked', true)->count();
        $topThree = Keyword::where('is_tracked', true)->whereBetween('current_position', [1, 3])->count();
        $pageOne = Keyword::where('is_tracked', true)->whereBetween('current_position', [1, 10])->count();

        // Visibility index 0-100: average reach (101 - position, floored at 0) over tracked keywords.
        $positions = Keyword::where('is_tracked', true)->whereNotNull('current_position')->pluck('current_position');
        $visibility = $positions->isNotEmpty()
            ? (int) round($positions->avg(fn ($p) => $p >= 1 && $p <= 100 ? (101 - $p) : 0))
            : 0;

        return [
            'tracked_keywords' => $tracked,
            'top_three' => $topThree,
            'page_one' => $pageOne,
            'visibility' => $visibility,
        ];
    }

    /**
     * Recurring + contract revenue from won deals (minor units).
     *
     * @return array<string, int>
     */
    public function revenue(): array
    {
        $won = Deal::whereHas('stage', fn ($q) => $q->where('is_won', true));

        return [
            'mrr' => (int) (clone $won)->sum('mrr'),
            'arr' => (int) (clone $won)->sum('arr'),
            'contract_value' => (int) (clone $won)->sum('value'),
            'ltv' => (int) (clone $won)->sum('ltv'),
        ];
    }

    /**
     * Lead volume by acquisition channel (contacts grouped by lead_source).
     *
     * @return array<string, int>
     */
    public function sourceBreakdown(): array
    {
        return Contact::query()
            ->selectRaw("COALESCE(NULLIF(lead_source, ''), 'direct') AS channel, COUNT(*) AS total")
            ->groupBy('channel')
            ->pluck('total', 'channel')
            ->map(fn ($n) => (int) $n)
            ->all();
    }
}
