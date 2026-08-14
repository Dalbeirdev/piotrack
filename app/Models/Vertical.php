<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An industry vertical (VERT), carrying the compliance framing that vertical
 * messaging leans on (VERT-020).
 *
 * @property int $id
 * @property string $key
 * @property string $name
 * @property string|null $compliance_notes
 * @property bool $is_active
 */
class Vertical extends Model
{
    use BelongsToTenant;

    protected $fillable = ['organization_id', 'key', 'name', 'description', 'compliance_notes', 'is_active'];

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
