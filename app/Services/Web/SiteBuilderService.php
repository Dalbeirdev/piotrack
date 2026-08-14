<?php

namespace App\Services\Web;

use App\Models\PageSection;
use App\Models\SitePage;
use App\Support\AuditLogger;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The website page builder (WEB). Typed pages built from ordered section blocks,
 * wired to navigation, and published to a public URL.
 */
class SiteBuilderService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createPage(array $data): SitePage
    {
        $page = SitePage::create([
            'type' => $data['type'] ?? 'landing',
            'slug' => $this->uniqueSlug($data['slug'] ?? $data['title']),
            'title' => $data['title'],
            'meta_title' => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
            'headline' => $data['headline'] ?? null,
            'subheadline' => $data['subheadline'] ?? null,
            'template' => $data['template'] ?? 'standard',
            // Column defaults are not hydrated onto the in-memory model, so
            // anything read back in the same request is set explicitly.
            'status' => SitePage::STATUS_DRAFT,
            'view_count' => 0,
            'service_line_id' => $data['service_line_id'] ?? null,
            'vertical_id' => $data['vertical_id'] ?? null,
            'seo_location_id' => $data['seo_location_id'] ?? null,
            'form_id' => $data['form_id'] ?? null,
        ]);

        $this->audit->log('web.page.created', context: ['type' => $page->type, 'slug' => $page->slug],
            resourceType: 'site_page', resourceId: (string) $page->id);

        return $page;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function addSection(SitePage $page, array $data): PageSection
    {
        $type = $data['type'];

        if (! in_array($type, PageSection::TYPES, true)) {
            throw new RuntimeException("Unknown section type [{$type}].");
        }

        return PageSection::create([
            'site_page_id' => $page->id,
            'type' => $type,
            'heading' => $data['heading'] ?? null,
            'body' => $data['body'] ?? null,
            'settings' => $data['settings'] ?? null,
            'sort_order' => $data['sort_order'] ?? ((int) PageSection::where('site_page_id', $page->id)->max('sort_order') + 1),
            'is_visible' => $data['is_visible'] ?? true,
        ]);
    }

    /**
     * Reorder sections to the given id order.
     *
     * @param  list<int>  $orderedIds
     */
    public function reorderSections(SitePage $page, array $orderedIds): void
    {
        foreach ($orderedIds as $index => $id) {
            PageSection::where('site_page_id', $page->id)->whereKey($id)->update(['sort_order' => $index]);
        }
    }

    /**
     * Publish a page. A page with no title or no visible section cannot go live —
     * an empty published URL is worse than no URL.
     */
    public function publish(SitePage $page): SitePage
    {
        if (trim($page->title) === '') {
            throw new RuntimeException('A page needs a title before it can be published.');
        }

        if (! PageSection::where('site_page_id', $page->id)->where('is_visible', true)->exists()) {
            throw new RuntimeException('A page needs at least one visible section before it can be published.');
        }

        $page->update(['status' => SitePage::STATUS_PUBLISHED, 'published_at' => now()]);

        $this->audit->log('web.page.published', context: ['slug' => $page->slug],
            resourceType: 'site_page', resourceId: (string) $page->id);

        return $page->refresh();
    }

    public function unpublish(SitePage $page): SitePage
    {
        $page->update(['status' => SitePage::STATUS_DRAFT, 'published_at' => null]);

        $this->audit->log('web.page.unpublished', context: ['slug' => $page->slug],
            resourceType: 'site_page', resourceId: (string) $page->id);

        return $page->refresh();
    }

    /**
     * A URL-safe slug unique across ALL tenants: published pages share one public
     * URL space (/s/{slug}), so uniqueness must be global or one tenant's page
     * would shadow another's.
     */
    public function uniqueSlug(string $source): string
    {
        $base = Str::slug($source) ?: 'page';
        $slug = $base;
        $suffix = 2;

        while (SitePage::withoutGlobalScope('tenant')->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
