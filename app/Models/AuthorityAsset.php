<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $achieved_on
 * @property string $type
 */
class AuthorityAsset extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id', 'type', 'name', 'issuer', 'url', 'image_url', 'achieved_on',
    ];

    protected function casts(): array
    {
        return ['achieved_on' => 'date'];
    }
}
