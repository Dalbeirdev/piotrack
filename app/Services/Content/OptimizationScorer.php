<?php

namespace App\Services\Content;

use App\Models\ContentPiece;

/**
 * Scores a content piece's on-page optimization readiness (CONT-032/037/038/039).
 * Pure heuristic over the piece's own fields — title length, target keyword, CTA,
 * excerpt, content depth, internal links.
 */
class OptimizationScorer
{
    public function score(ContentPiece $piece): int
    {
        $titleLength = mb_strlen((string) $piece->title);
        $words = str_word_count(strip_tags((string) $piece->body));
        $internalLinks = substr_count((string) $piece->body, 'href=');

        $checks = [
            ['weight' => 20, 'ok' => $titleLength >= 30 && $titleLength <= 70],
            ['weight' => 20, 'ok' => ! empty($piece->target_keyword)],
            ['weight' => 15, 'ok' => ! empty($piece->cta)],
            ['weight' => 15, 'ok' => ! empty($piece->excerpt)],
            ['weight' => 20, 'ok' => $words >= 600],
            ['weight' => 10, 'ok' => $internalLinks >= 2],
        ];

        // The weights are a fixed set summing to 100, so no divisor guard is needed.
        $total = array_sum(array_column($checks, 'weight'));
        $earned = array_sum(array_map(fn (array $c) => $c['ok'] ? $c['weight'] : 0, $checks));

        return (int) round($earned / $total * 100);
    }
}
