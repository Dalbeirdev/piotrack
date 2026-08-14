<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * @property array<int, array<string, mixed>>|null $checks
 * @property int $score
 * @property int $issues_count
 */
class SeoAudit extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id', 'url', 'score', 'checks', 'issues_count', 'fetched_status',
    ];

    protected function casts(): array
    {
        return ['checks' => 'array'];
    }
}
