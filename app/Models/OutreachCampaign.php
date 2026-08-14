<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $type
 * @property string $status
 */
class OutreachCampaign extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id', 'name', 'type', 'goal', 'status',
    ];

    /**
     * @return HasMany<OutreachProspect, $this>
     */
    public function prospects(): HasMany
    {
        return $this->hasMany(OutreachProspect::class);
    }
}
