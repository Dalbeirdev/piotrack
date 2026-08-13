<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $channel
 * @property string $address
 * @property string $reason
 */
class Suppression extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id', 'channel', 'address', 'reason', 'contact_id',
    ];
}
