<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An MSP service line (SVC). Pages, campaigns, keywords and content target it.
 *
 * @property int $id
 * @property string $key
 * @property string $name
 * @property string|null $category
 * @property bool $is_active
 */
class ServiceLine extends Model
{
    use BelongsToTenant;

    protected $fillable = ['organization_id', 'key', 'name', 'category', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * @return HasMany<SitePage, $this>
     */
    public function pages(): HasMany
    {
        return $this->hasMany(SitePage::class);
    }
}
