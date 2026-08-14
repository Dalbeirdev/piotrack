<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SeoLocation extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id', 'name', 'street', 'city', 'region', 'postal_code',
        'country', 'phone', 'website',
    ];

    /**
     * @return HasMany<Citation, $this>
     */
    public function citations(): HasMany
    {
        return $this->hasMany(Citation::class);
    }
}
