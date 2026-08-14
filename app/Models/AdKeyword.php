<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $match_type
 * @property bool $is_negative
 */
class AdKeyword extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id', 'ad_group_id', 'phrase', 'match_type', 'is_negative',
    ];

    protected function casts(): array
    {
        return ['is_negative' => 'boolean'];
    }

    /**
     * @return BelongsTo<AdGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(AdGroup::class, 'ad_group_id');
    }
}
