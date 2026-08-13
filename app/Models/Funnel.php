<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Funnel extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id', 'name', 'description',
    ];

    /**
     * @return HasMany<FunnelStage, $this>
     */
    public function stages(): HasMany
    {
        return $this->hasMany(FunnelStage::class)->orderBy('position');
    }
}
