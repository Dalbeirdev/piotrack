<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string|null $domain
 * @property bool $is_tracked
 */
class Competitor extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id', 'name', 'domain', 'notes', 'is_tracked',
    ];

    protected function casts(): array
    {
        return ['is_tracked' => 'boolean'];
    }
}
