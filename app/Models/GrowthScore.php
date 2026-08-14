<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $overall
 * @property array<string, int> $breakdown
 * @property array<int, array<string, string>> $recommendations
 * @property Carbon $computed_on
 */
class GrowthScore extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id', 'overall', 'breakdown', 'recommendations', 'computed_on',
    ];

    protected function casts(): array
    {
        return [
            'overall' => 'integer',
            'breakdown' => 'array',
            'recommendations' => 'array',
            'computed_on' => 'date',
        ];
    }
}
