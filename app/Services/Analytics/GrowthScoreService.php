<?php

namespace App\Services\Analytics;

use App\Models\AiVisibilityCheck;
use App\Models\AuthorityAsset;
use App\Models\Citation;
use App\Models\ContentPiece;
use App\Models\GrowthScore;
use App\Models\Review;
use App\Models\SeoAudit;
use App\Models\Workflow;
use App\Support\AuditLogger;
use App\Support\CurrentOrganization;

/**
 * MSP Growth Score (GSCORE) — the flagship 0-100 composite. Each sub-score is
 * computed from the real data of its module (SEO, local, website, authority,
 * AI-visibility, paid, content, conversion, automation, sales velocity). A module
 * with no data yet scores null (excluded from the weighted overall, never
 * invented) and surfaces a "start measuring" recommendation. Snapshots persist
 * for trend tracking.
 */
class GrowthScoreService
{
    /** @var array<string, float> */
    public const WEIGHTS = [
        'seo' => 0.12, 'local' => 0.10, 'website' => 0.10, 'authority' => 0.10,
        'ai_visibility' => 0.10, 'paid' => 0.10, 'content' => 0.10, 'conversion' => 0.13,
        'automation' => 0.05, 'sales_velocity' => 0.10,
    ];

    /** @var array<string, string> */
    private const ACTIONS = [
        'seo' => 'Improve rankings for tracked keywords to lift organic visibility.',
        'local' => 'Fix NAP inconsistencies and add local citations.',
        'website' => 'Resolve on-page issues from the latest technical audit.',
        'authority' => 'Gather more reviews and publish authority assets.',
        'ai_visibility' => 'Optimize content so AI assistants recommend you.',
        'paid' => 'Tune ad targeting and creative to raise ROAS.',
        'content' => 'Raise content optimization scores and publish more.',
        'conversion' => 'Optimize funnels and CTAs to convert more leads to SQLs.',
        'automation' => 'Activate marketing workflows to nurture leads automatically.',
        'sales_velocity' => 'Advance more opportunities to close to raise win rate.',
    ];

    public function __construct(
        private CurrentOrganization $current,
        private AnalyticsService $analytics,
        private AuditLogger $audit,
    ) {}

    /**
     * @return array{overall: int, breakdown: array<string, int|null>, recommendations: list<array{area: string, score: int|null, action: string}>}
     */
    public function compute(): array
    {
        $breakdown = [
            'seo' => $this->seoScore(),
            'local' => $this->localScore(),
            'website' => $this->websiteScore(),
            'authority' => $this->authorityScore(),
            'ai_visibility' => $this->aiVisibilityScore(),
            'paid' => $this->paidScore(),
            'content' => $this->contentScore(),
            'conversion' => $this->conversionScore(),
            'automation' => $this->automationScore(),
            'sales_velocity' => $this->salesVelocityScore(),
        ];

        return [
            'overall' => $this->overall($breakdown),
            'breakdown' => $breakdown,
            'recommendations' => $this->recommendations($breakdown),
        ];
    }

    /**
     * Weighted overall over the sub-scores that have data (weights renormalized).
     *
     * @param  array<string, int|null>  $breakdown
     */
    public function overall(array $breakdown): int
    {
        $weightSum = 0.0;
        $acc = 0.0;
        foreach ($breakdown as $key => $score) {
            if ($score === null) {
                continue;
            }
            $weight = self::WEIGHTS[$key] ?? 0.0;
            $acc += $score * $weight;
            $weightSum += $weight;
        }

        return $weightSum > 0 ? (int) round($acc / $weightSum) : 0;
    }

    /**
     * Prioritized actions: lowest present sub-scores under 70, then a nudge for
     * each module with no data yet (capped).
     *
     * @param  array<string, int|null>  $breakdown
     * @return list<array{area: string, score: int|null, action: string}>
     */
    public function recommendations(array $breakdown): array
    {
        $present = array_filter($breakdown, fn ($s) => $s !== null);
        asort($present);

        $recs = [];
        foreach ($present as $area => $score) {
            if ($score < 70) {
                $recs[] = ['area' => $area, 'score' => $score, 'action' => self::ACTIONS[$area] ?? ''];
            }
        }

        foreach ($breakdown as $area => $score) {
            if ($score === null) {
                $recs[] = ['area' => $area, 'score' => null, 'action' => 'Start using '.str_replace('_', ' ', $area).' to measure this score.'];
            }
        }

        return array_slice($recs, 0, 5);
    }

    /**
     * Persist today's snapshot (one per org per day) for trend tracking.
     */
    public function snapshot(): GrowthScore
    {
        $computed = $this->compute();

        // The `date` cast stores midnight, so the lookup key must be normalized the
        // same way — a raw 'Y-m-d' string never matches and would insert a duplicate.
        $score = GrowthScore::updateOrCreate(
            ['organization_id' => $this->current->id(), 'computed_on' => now()->startOfDay()],
            ['overall' => $computed['overall'], 'breakdown' => $computed['breakdown'], 'recommendations' => $computed['recommendations']],
        );

        $this->audit->log('analytics.growth_score.computed', context: ['overall' => $computed['overall']], resourceType: 'growth_score', resourceId: (string) $score->id);

        return $score;
    }

    /**
     * Recent snapshots (oldest→newest) for the trend chart.
     *
     * @return list<array{date: string, overall: int}>
     */
    public function trend(int $limit = 30): array
    {
        return GrowthScore::orderByDesc('computed_on')->limit($limit)->get()
            ->reverse()
            ->map(fn (GrowthScore $g) => ['date' => $g->computed_on->toDateString(), 'overall' => $g->overall])
            ->values()
            ->all();
    }

    private function seoScore(): ?int
    {
        $seo = $this->analytics->seo();

        return $seo['tracked_keywords'] > 0
            ? (int) round($seo['page_one'] / $seo['tracked_keywords'] * 100)
            : null;
    }

    private function websiteScore(): ?int
    {
        $score = SeoAudit::latest('id')->value('score');

        return $score !== null ? (int) $score : null;
    }

    private function localScore(): ?int
    {
        $total = Citation::count();
        if ($total === 0) {
            return null;
        }

        return (int) round(Citation::where('status', 'consistent')->count() / $total * 100);
    }

    private function authorityScore(): ?int
    {
        $reviews = Review::whereNotNull('rating')->count();
        if ($reviews > 0) {
            return (int) round((float) Review::whereNotNull('rating')->avg('rating') * 20);
        }
        $assets = AuthorityAsset::count();

        return $assets > 0 ? min(100, $assets * 20) : null;
    }

    private function aiVisibilityScore(): ?int
    {
        $total = AiVisibilityCheck::count();

        return $total > 0
            ? (int) round(AiVisibilityCheck::where('mentioned', true)->count() / $total * 100)
            : null;
    }

    private function paidScore(): ?int
    {
        $ads = $this->analytics->advertising();
        if ($ads->spend === 0) {
            return null;
        }

        // ROAS of 4x (400%) or better is a full score.
        return min(100, (int) round($ads->roas / 4 * 100));
    }

    private function contentScore(): ?int
    {
        $pieces = ContentPiece::whereNotNull('optimization_score')->count();

        return $pieces > 0
            ? (int) round((float) ContentPiece::whereNotNull('optimization_score')->avg('optimization_score'))
            : null;
    }

    private function conversionScore(): ?int
    {
        $funnel = $this->analytics->funnel();
        if ($funnel['leads'] === 0) {
            return null;
        }

        // Share of leads that reach SQL, as a 0-100 conversion-health proxy.
        return min(100, (int) round($funnel['sqls'] / $funnel['leads'] * 100));
    }

    private function automationScore(): ?int
    {
        $total = Workflow::count();
        if ($total === 0) {
            return null;
        }

        // Four or more active workflows is a full score.
        return min(100, Workflow::where('status', 'active')->count() * 25);
    }

    private function salesVelocityScore(): ?int
    {
        $funnel = $this->analytics->funnel();
        $closed = $funnel['closed_won'] + $funnel['closed_lost'];
        $total = $funnel['opportunities'] + $closed;
        if ($total === 0) {
            return null;
        }

        // Win rate across all opportunities as a velocity proxy.
        return min(100, (int) round($funnel['closed_won'] / $total * 100));
    }
}
