<?php

namespace App\Services\Seo;

use DOMDocument;
use DOMXPath;

/**
 * Scores a page's machine-readability for answer/generative/LLM engines
 * (AEO-002/003/008/017, LLMO-001/002/017). Heuristic + in-house: semantic HTML,
 * structured data, heading structure, FAQ/answer blocks, lists/tables, content
 * depth. Returns 0–100 plus per-factor detail.
 */
class ContentReadinessScorer
{
    /**
     * @return array{score: int, factors: list<array{key: string, label: string, ok: bool, detail: string}>}
     */
    public function score(string $html): array
    {
        $doc = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($doc);

        $has = function (string $expr) use ($xpath): bool {
            $nodes = $xpath->query($expr);

            return $nodes !== false && $nodes->length > 0;
        };

        $bodyNodes = $xpath->query('//body');
        $body = $bodyNodes !== false ? $bodyNodes->item(0) : null;
        $words = str_word_count($body !== null ? trim($body->textContent) : '');

        $factors = [
            $this->factor('semantic_html', 'Semantic HTML5', $has('//main | //article | //section'), 'Uses main/article/section landmarks.', 'No semantic landmarks (main/article/section).'),
            $this->factor('structured_data', 'Structured data', $has('//script[@type="application/ld+json"]'), 'JSON-LD present for machine reading.', 'No JSON-LD structured data.'),
            $this->factor('headings', 'Heading structure', $has('//h1') && $has('//h2'), 'Clear H1 + H2 structure.', 'Weak heading structure.'),
            $this->factor('faq', 'Question/answer blocks', $has('//*[self::h2 or self::h3][contains(text(), "?")]') || $has('//dl') || $has('//*[contains(@class,"faq")]'), 'Answer-first Q&A blocks found.', 'No question/answer blocks.'),
            $this->factor('lists', 'Lists & tables', $has('//ul | //ol | //table'), 'Scannable lists/tables present.', 'No lists or tables.'),
            $this->factor('depth', 'Content depth', $words >= 300, "{$words} words of extractable content.", "Thin content ({$words} words)."),
        ];

        $passed = count(array_filter($factors, fn (array $f) => $f['ok']));
        $score = (int) round($passed / count($factors) * 100);

        return ['score' => $score, 'factors' => $factors];
    }

    /**
     * @return array{key: string, label: string, ok: bool, detail: string}
     */
    private function factor(string $key, string $label, bool $ok, string $okDetail, string $failDetail): array
    {
        return ['key' => $key, 'label' => $label, 'ok' => $ok, 'detail' => $ok ? $okDetail : $failDetail];
    }
}
