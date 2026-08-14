<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $status
 */
class Ad extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id', 'ad_group_id', 'name', 'headline', 'body', 'cta', 'destination_url', 'status',
    ];

    /**
     * @return BelongsTo<AdGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(AdGroup::class, 'ad_group_id');
    }
}
