<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $trigger
 * @property int $threshold
 * @property string $channel
 * @property bool $is_active
 */
class AlertRule extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id', 'name', 'trigger', 'threshold', 'channel', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
