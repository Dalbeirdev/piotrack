<?php

namespace App\Services\Analytics;

use App\Models\Keyword;
use App\Models\KeywordRanking;

/**
 * Competitive intelligence (CINT). Computes share-of-voice / market-share-of-
 * search across the tenant's tracked keywords from our own ranking data: our
 * organic visibility vs each competitor's captured positions. Deeper competitor
 * monitoring (PPC, ads, backlinks, content, maps, reviews, social) needs external
 * SERP/Ahrefs/SEMrush providers and is Planned.
 */
class CompetitiveService
{
    /**
     * Search visibility contributed by a ranking position (0..100): higher for
     * better positions, zero past the first hundred results.
     */
    private function visibility(int $position): int
    {
        return $position >= 1 && $position <= 100 ? (101 - $position) : 0;
    }

    /**
     * Our own organic visibility across tracked keywords.
     */
    public function ourVisibility(): int
    {
        return (int) Keyword::where('is_tracked', true)
            ->whereNotNull('current_position')
            ->get(['current_position'])
            ->sum(fn (Keyword $k) => $this->visibility((int) $k->current_position));
    }

    /**
     * Competitor visibility per domain from captured competitor rankings.
     *
     * @return array<string, int>
     */
    public function competitorVisibility(): array
    {
        $out = [];
        KeywordRanking::where('is_competitor', true)
            ->whereNotNull('competitor_domain')
            ->get(['competitor_domain', 'position'])
            ->each(function (KeywordRanking $r) use (&$out) {
                $domain = (string) $r->competitor_domain;
                $out[$domain] = ($out[$domain] ?? 0) + $this->visibility((int) $r->position);
            });

        return $out;
    }

    /**
     * Share of voice: our share of total search visibility vs all tracked
     * competitors, plus each competitor's share (percentages).
     *
     * @return array{our_visibility: int, our_share: float, competitors: list<array{domain: string, visibility: int, share: float}>}
     */
    public function shareOfVoice(): array
    {
        $ours = $this->ourVisibility();
        $competitors = $this->competitorVisibility();
        $total = $ours + array_sum($competitors);

        $share = fn (int $v) => $total > 0 ? round(($v / $total) * 100, 2) : 0.0;

        $competitorRows = [];
        foreach ($competitors as $domain => $visibility) {
            $competitorRows[] = ['domain' => $domain, 'visibility' => $visibility, 'share' => $share($visibility)];
        }
        usort($competitorRows, fn ($a, $b) => $b['visibility'] <=> $a['visibility']);

        return [
            'our_visibility' => $ours,
            'our_share' => $share($ours),
            'competitors' => $competitorRows,
        ];
    }
}
