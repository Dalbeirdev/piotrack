<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $category
 * @property string $attribute
 * @property string $operator
 * @property string|null $value
 * @property int $points
 * @property bool $is_active
 */
class ScoringRule extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id', 'name', 'category', 'attribute', 'operator', 'value', 'points', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
