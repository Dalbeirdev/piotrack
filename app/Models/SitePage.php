<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A page on the MSP website (WEB). One model serves the whole page architecture
 * — service, vertical, location, landing, campaign — because they differ by
 * binding and sections, not by structure.
 *
 * @property int $id
 * @property string $type
 * @property string $slug
 * @property string $title
 * @property string $status
 * @property int $view_count
 * @property Carbon|null $published_at
 * @property int|null $service_line_id
 * @property int|null $vertical_id
 * @property int|null $seo_location_id
 * @property int|null $form_id
 * @property-read Collection<int, PageSection> $sections
 */
class SitePage extends Model
{
    use BelongsToTenant;

    public const TYPES = ['home', 'service', 'vertical', 'location', 'landing', 'campaign', 'resource'];

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    protected $fillable = [
        'organization_id', 'type', 'slug', 'title', 'meta_title', 'meta_description',
        'headline', 'subheadline', 'template', 'status', 'published_at', 'view_count',
        'service_line_id', 'vertical_id', 'seo_location_id', 'form_id',
    ];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'view_count' => 'integer'];
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    /**
     * @return HasMany<PageSection, $this>
     */
    public function sections(): HasMany
    {
        return $this->hasMany(PageSection::class)->orderBy('sort_order');
    }

    /**
     * @return BelongsTo<ServiceLine, $this>
     */
    public function serviceLine(): BelongsTo
    {
        return $this->belongsTo(ServiceLine::class);
    }

    /**
     * @return BelongsTo<Vertical, $this>
     */
    public function vertical(): BelongsTo
    {
        return $this->belongsTo(Vertical::class);
    }

    /**
     * @return BelongsTo<SeoLocation, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(SeoLocation::class, 'seo_location_id');
    }

    /**
     * The conversion form a CTA points at (WEB-019/020/021).
     *
     * @return BelongsTo<Form, $this>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }
}
