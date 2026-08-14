<?php

namespace App\Services\Web;

use App\Models\PageSection;
use App\Models\SitePage;

/**
 * Per-page website health (WEB-045/048/051/055). Every check reads the page's
 * real content: a check with no data FAILS rather than passing by default, so a
 * blank page scores zero instead of looking healthy.
 *
 * This complements rather than duplicates the Stage 7 TechnicalSeoAuditor: that
 * fetches and analyses a live URL's HTML, this scores what the tenant has
 * actually built in the page model, before it is ever published.
 */
class SiteHealthService
{
    /** Meta description length bounds search engines actually use. */
    private const META_MIN = 50;

    private const META_MAX = 160;

    /**
     * @return array{score: int, checks: list<array{check: string, passed: bool, detail: string}>}
     */
    public function score(SitePage $page): array
    {
        $sections = PageSection::where('site_page_id', $page->id)->where('is_visible', true)->get();

        $metaTitle = (string) ($page->meta_title ?: $page->title);
        $metaDescription = (string) $page->meta_description;
        $hasConversionSection = $sections->contains(fn (PageSection $s) => in_array($s->type, PageSection::CONVERSION_TYPES, true));
        $hasProof = $sections->contains(fn (PageSection $s) => in_array($s->type, ['trust', 'reviews', 'testimonials', 'logos', 'case_studies', 'awards'], true));

        $checks = [
            $this->check('Meta title present', $metaTitle !== '', $metaTitle !== '' ? 'Set' : 'Missing'),
            $this->check(
                'Meta description usable',
                mb_strlen($metaDescription) >= self::META_MIN && mb_strlen($metaDescription) <= self::META_MAX,
                $metaDescription === '' ? 'Missing' : mb_strlen($metaDescription).' characters',
            ),
            $this->check('Headline set', (string) $page->headline !== '', (string) $page->headline !== '' ? 'Set' : 'Missing'),
            $this->check('Has visible sections', $sections->isNotEmpty(), $sections->count().' visible'),
            $this->check('Has a call to action', $hasConversionSection, $hasConversionSection ? 'Present' : 'No CTA or offer section'),
            $this->check(
                'Conversion path wired',
                $page->form_id !== null || $hasConversionSection,
                $page->form_id !== null ? 'Form attached' : ($hasConversionSection ? 'CTA section only' : 'None'),
            ),
            $this->check('Includes third-party proof', $hasProof, $hasProof ? 'Present' : 'No trust, review or case-study section'),
            $this->check('Published', $page->isPublished(), $page->status),
        ];

        $passed = count(array_filter($checks, fn (array $c) => $c['passed']));

        return [
            'score' => (int) round($passed / count($checks) * 100),
            'checks' => $checks,
        ];
    }

    /**
     * Site-wide rollup: the weakest pages first, so the next fix is obvious.
     *
     * @return array{pages: int, published: int, average_score: int, weakest: list<array{id: int, title: string, slug: string, score: int}>}
     */
    public function siteReport(): array
    {
        $pages = SitePage::all();

        if ($pages->isEmpty()) {
            return ['pages' => 0, 'published' => 0, 'average_score' => 0, 'weakest' => []];
        }

        $scored = $pages->map(fn (SitePage $p) => [
            'id' => $p->id,
            'title' => $p->title,
            'slug' => $p->slug,
            'score' => $this->score($p)['score'],
        ])->sortBy('score')->values();

        return [
            'pages' => $pages->count(),
            'published' => $pages->where('status', SitePage::STATUS_PUBLISHED)->count(),
            'average_score' => (int) round($scored->avg('score')),
            'weakest' => $scored->take(5)->all(),
        ];
    }

    /**
     * @return array{check: string, passed: bool, detail: string}
     */
    private function check(string $name, bool $passed, string $detail): array
    {
        return ['check' => $name, 'passed' => $passed, 'detail' => $detail];
    }
}
