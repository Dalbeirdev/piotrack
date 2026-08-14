<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property array<int, string>|null $mismatches
 * @property string $status
 * @property Carbon|null $checked_at
 */
class Citation extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id', 'seo_location_id', 'source', 'listed_name', 'listed_address',
        'listed_phone', 'url', 'status', 'mismatches', 'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'mismatches' => 'array',
            'checked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<SeoLocation, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(SeoLocation::class, 'seo_location_id');
    }
}
