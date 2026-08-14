<?php

namespace App\Services\Seo;

use App\Models\SeoAudit;
use App\Seo\AuditResult;
use App\Support\AuditLogger;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Real on-page technical SEO auditor (TSEO). Fetches a URL and analyses its HTML
 * with PHP's built-in DOM parser — no crawler SaaS (ADR-0005). `analyze()` is a
 * pure function of (html, url) and is directly tested; `crawl()` fetches then
 * analyses and persists a `seo_audits` row.
 */
class TechnicalSeoAuditor
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * Fetch and audit a URL, persisting the result. A fetch failure records a
     * failed audit rather than throwing.
     */
    public function crawl(string $url): SeoAudit
    {
        try {
            $response = Http::timeout(15)->get($url);
            $status = $response->status();

            if ($response->failed()) {
                return $this->persistFailed($url, $status);
            }

            return $this->persist($url, $this->analyze($response->body(), $url), $status);
        } catch (Throwable) {
            return $this->persistFailed($url, null);
        }
    }

    public function analyze(string $html, string $url): AuditResult
    {
        $doc = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        // Prefix forces UTF-8 handling of the fragment.
        $doc->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($doc);
        $checks = [];

        $checks[] = $this->titleCheck($xpath);
        $checks[] = $this->metaDescriptionCheck($xpath);
        $checks[] = $this->h1Check($xpath);
        $checks[] = $this->subheadingsCheck($xpath);
        $checks[] = $this->canonicalCheck($xpath);
        $checks[] = $this->viewportCheck($xpath);
        $checks[] = $this->robotsMetaCheck($xpath);
        $checks[] = $this->imageAltCheck($xpath);
        $checks[] = $this->structuredDataCheck($xpath);
        $checks[] = $this->openGraphCheck($xpath);
        $checks[] = $this->langCheck($xpath);
        $checks[] = $this->httpsCheck($url);
        $checks[] = $this->wordCountCheck($xpath);

        $totalWeight = array_sum(array_column($checks, 'weight'));
        $earned = 0.0;
        foreach ($checks as $c) {
            $earned += match ($c['status']) {
                'pass' => $c['weight'],
                'warn' => $c['weight'] / 2,
                default => 0,
            };
        }

        $score = $totalWeight > 0 ? (int) round($earned / $totalWeight * 100) : 0;
        $issues = count(array_filter($checks, fn (array $c) => $c['status'] !== 'pass'));

        return new AuditResult($score, $checks, $issues);
    }

    private function persist(string $url, AuditResult $result, ?int $status): SeoAudit
    {
        $audit = SeoAudit::create([
            'url' => $url,
            'score' => $result->score,
            'checks' => $result->checksForStorage(),
            'issues_count' => $result->issuesCount,
            'fetched_status' => $status,
        ]);

        $this->audit->log('seo.audit.run', context: ['url' => $url, 'score' => $result->score], resourceType: 'seo_audit', resourceId: (string) $audit->id, organizationId: $audit->organization_id);

        return $audit;
    }

    private function persistFailed(string $url, ?int $status): SeoAudit
    {
        return SeoAudit::create([
            'url' => $url,
            'score' => 0,
            'checks' => [['key' => 'fetch', 'label' => 'Page fetch', 'status' => 'fail', 'detail' => 'Could not fetch the page'.($status !== null ? " (HTTP {$status})" : '.')]],
            'issues_count' => 1,
            'fetched_status' => $status,
        ]);
    }

    // ---------------------------------------------------------------------
    // Individual checks — each returns {key,label,status,detail,weight}.
    // ---------------------------------------------------------------------

    /** @return array{key: string, label: string, status: string, detail: string, weight: int} */
    private function titleCheck(DOMXPath $xpath): array
    {
        $title = $this->firstText($xpath, '//title');
        $len = mb_strlen($title);

        if ($title === '') {
            return $this->result('title', 'Title tag', 'fail', 'Missing <title>.', 15);
        }
        if ($len < 10 || $len > 60) {
            return $this->result('title', 'Title tag', 'warn', "Title is {$len} chars (aim 10–60).", 15);
        }

        return $this->result('title', 'Title tag', 'pass', "Title present ({$len} chars).", 15);
    }

    /** @return array{key: string, label: string, status: string, detail: string, weight: int} */
    private function metaDescriptionCheck(DOMXPath $xpath): array
    {
        $desc = $this->firstAttr($xpath, '//meta[@name="description"]/@content');
        $len = mb_strlen($desc);

        if ($desc === '') {
            return $this->result('meta_description', 'Meta description', 'fail', 'Missing meta description.', 10);
        }
        if ($len < 50 || $len > 160) {
            return $this->result('meta_description', 'Meta description', 'warn', "Description is {$len} chars (aim 50–160).", 10);
        }

        return $this->result('meta_description', 'Meta description', 'pass', "Description present ({$len} chars).", 10);
    }

    /** @return array{key: string, label: string, status: string, detail: string, weight: int} */
    private function h1Check(DOMXPath $xpath): array
    {
        $count = $this->count($xpath, '//h1');

        if ($count === 0) {
            return $this->result('h1', 'H1 heading', 'fail', 'No H1 heading found.', 15);
        }
        if ($count > 1) {
            return $this->result('h1', 'H1 heading', 'warn', "{$count} H1 headings (use one).", 15);
        }

        return $this->result('h1', 'H1 heading', 'pass', 'Exactly one H1.', 15);
    }

    /** @return array{key: string, label: string, status: string, detail: string, weight: int} */
    private function subheadingsCheck(DOMXPath $xpath): array
    {
        $count = $this->count($xpath, '//h2 | //h3');

        return $count === 0
            ? $this->result('subheadings', 'Subheadings', 'warn', 'No H2/H3 subheadings.', 5)
            : $this->result('subheadings', 'Subheadings', 'pass', "{$count} subheadings.", 5);
    }

    /** @return array{key: string, label: string, status: string, detail: string, weight: int} */
    private function canonicalCheck(DOMXPath $xpath): array
    {
        $has = $this->count($xpath, '//link[@rel="canonical"]') > 0;

        return $has
            ? $this->result('canonical', 'Canonical URL', 'pass', 'Canonical link present.', 8)
            : $this->result('canonical', 'Canonical URL', 'warn', 'No canonical link.', 8);
    }

    /** @return array{key: string, label: string, status: string, detail: string, weight: int} */
    private function viewportCheck(DOMXPath $xpath): array
    {
        $has = $this->count($xpath, '//meta[@name="viewport"]') > 0;

        return $has
            ? $this->result('viewport', 'Mobile viewport', 'pass', 'Viewport meta present.', 10)
            : $this->result('viewport', 'Mobile viewport', 'fail', 'Missing viewport meta (not mobile-friendly).', 10);
    }

    /** @return array{key: string, label: string, status: string, detail: string, weight: int} */
    private function robotsMetaCheck(DOMXPath $xpath): array
    {
        $robots = mb_strtolower($this->firstAttr($xpath, '//meta[@name="robots"]/@content'));

        return str_contains($robots, 'noindex')
            ? $this->result('robots_meta', 'Indexability', 'fail', 'Page is set to noindex.', 12)
            : $this->result('robots_meta', 'Indexability', 'pass', 'Page is indexable.', 12);
    }

    /** @return array{key: string, label: string, status: string, detail: string, weight: int} */
    private function imageAltCheck(DOMXPath $xpath): array
    {
        $total = $this->count($xpath, '//img');

        if ($total === 0) {
            return $this->result('img_alt', 'Image alt text', 'pass', 'No images to check.', 6);
        }

        $missing = $this->count($xpath, '//img[not(@alt) or @alt=""]');

        return $missing === 0
            ? $this->result('img_alt', 'Image alt text', 'pass', "All {$total} images have alt text.", 6)
            : $this->result('img_alt', 'Image alt text', 'warn', "{$missing} of {$total} images missing alt text.", 6);
    }

    /** @return array{key: string, label: string, status: string, detail: string, weight: int} */
    private function structuredDataCheck(DOMXPath $xpath): array
    {
        $count = $this->count($xpath, '//script[@type="application/ld+json"]');

        return $count > 0
            ? $this->result('structured_data', 'Structured data', 'pass', "{$count} JSON-LD block(s).", 8)
            : $this->result('structured_data', 'Structured data', 'warn', 'No JSON-LD structured data.', 8);
    }

    /** @return array{key: string, label: string, status: string, detail: string, weight: int} */
    private function openGraphCheck(DOMXPath $xpath): array
    {
        $has = $this->count($xpath, '//meta[@property="og:title"]') > 0;

        return $has
            ? $this->result('open_graph', 'Open Graph', 'pass', 'Open Graph tags present.', 4)
            : $this->result('open_graph', 'Open Graph', 'warn', 'No Open Graph tags.', 4);
    }

    /** @return array{key: string, label: string, status: string, detail: string, weight: int} */
    private function langCheck(DOMXPath $xpath): array
    {
        $lang = $this->firstAttr($xpath, '//html/@lang');

        return $lang !== ''
            ? $this->result('lang', 'Language attribute', 'pass', "html lang=\"{$lang}\".", 3)
            : $this->result('lang', 'Language attribute', 'warn', 'No html lang attribute.', 3);
    }

    /** @return array{key: string, label: string, status: string, detail: string, weight: int} */
    private function httpsCheck(string $url): array
    {
        return str_starts_with(mb_strtolower($url), 'https://')
            ? $this->result('https', 'HTTPS', 'pass', 'Served over HTTPS.', 10)
            : $this->result('https', 'HTTPS', 'fail', 'Not served over HTTPS.', 10);
    }

    /** @return array{key: string, label: string, status: string, detail: string, weight: int} */
    private function wordCountCheck(DOMXPath $xpath): array
    {
        $bodyNodes = $xpath->query('//body');
        $body = $bodyNodes !== false ? $bodyNodes->item(0) : null;
        $text = $body !== null ? $body->textContent : '';
        $words = str_word_count(trim($text));

        return $words >= 300
            ? $this->result('word_count', 'Content depth', 'pass', "{$words} words.", 4)
            : $this->result('word_count', 'Content depth', 'warn', "Thin content ({$words} words).", 4);
    }

    /** @return array{key: string, label: string, status: string, detail: string, weight: int} */
    private function result(string $key, string $label, string $status, string $detail, int $weight): array
    {
        return compact('key', 'label', 'status', 'detail', 'weight');
    }

    private function count(DOMXPath $xpath, string $expr): int
    {
        $nodes = $xpath->query($expr);

        return $nodes !== false ? $nodes->length : 0;
    }

    private function firstText(DOMXPath $xpath, string $expr): string
    {
        $nodes = $xpath->query($expr);
        $node = $nodes !== false ? $nodes->item(0) : null;

        return $node !== null ? trim($node->textContent) : '';
    }

    private function firstAttr(DOMXPath $xpath, string $expr): string
    {
        $nodes = $xpath->query($expr);
        $node = $nodes !== false ? $nodes->item(0) : null;

        return $node !== null ? trim($node->nodeValue ?? '') : '';
    }
}
