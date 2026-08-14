<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property array<int, string>|null $cited_sources
 * @property array<int, string>|null $competitors
 * @property bool $mentioned
 * @property int|null $share_of_answer
 * @property Carbon|null $checked_at
 */
class AiVisibilityCheck extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id', 'prompt', 'engine', 'brand', 'mentioned', 'position',
        'cited_sources', 'competitors', 'share_of_answer', 'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'mentioned' => 'boolean',
            'cited_sources' => 'array',
            'competitors' => 'array',
            'checked_at' => 'datetime',
        ];
    }
}
