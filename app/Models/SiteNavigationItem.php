<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A navigation entry (WEB-011/031). Strategic navigation is a tree, so items may
 * nest one level under a parent.
 *
 * @property int $id
 * @property string $label
 * @property string|null $url
 * @property string $placement
 * @property int $sort_order
 * @property int|null $site_page_id
 * @property int|null $parent_id
 */
class SiteNavigationItem extends Model
{
    use BelongsToTenant;

    protected $table = 'site_navigation';

    protected $fillable = [
        'organization_id', 'site_page_id', 'parent_id', 'label', 'url', 'placement', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    /**
     * @return HasMany<SiteNavigationItem, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }
}
