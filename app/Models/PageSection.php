<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * A content block on a page. Trust, review, testimonial, logo, case-study and
 * award blocks (WEB-023…029) are section types rather than bespoke pages.
 *
 * @property int $id
 * @property string $type
 * @property string|null $heading
 * @property string|null $body
 * @property array<string, mixed>|null $settings
 * @property int $sort_order
 * @property bool $is_visible
 */
class PageSection extends Model
{
    use BelongsToTenant;

    public const TYPES = [
        'hero', 'content', 'services', 'trust', 'reviews', 'testimonials',
        'logos', 'case_studies', 'awards', 'cta', 'faq', 'offer',
    ];

    /** Sections that constitute a call to action / conversion path. */
    public const CONVERSION_TYPES = ['cta', 'offer'];

    protected $fillable = [
        'organization_id', 'site_page_id', 'type', 'heading', 'body',
        'settings', 'sort_order', 'is_visible',
    ];

    protected function casts(): array
    {
        return ['settings' => 'array', 'sort_order' => 'integer', 'is_visible' => 'boolean'];
    }
}
