<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One piece of strategy work: an assessment, audit, research finding, roadmap
 * entry or initiative. The platform structures, assigns and tracks it; the
 * analysis itself is human consulting work (see the Stage 13 spec scope line).
 *
 * @property int $id
 * @property string $type
 * @property string $title
 * @property string $priority
 * @property string $status
 * @property string|null $source_module
 * @property Carbon|null $due_on
 * @property int|null $strategy_plan_id
 */
class StrategyItem extends Model
{
    use BelongsToTenant;

    public const TYPES = ['assessment', 'audit', 'research', 'roadmap', 'initiative'];

    protected $fillable = [
        'organization_id', 'strategy_plan_id', 'type', 'title', 'findings',
        'recommendation', 'priority', 'status', 'due_on', 'source_module',
    ];

    protected function casts(): array
    {
        return ['due_on' => 'date'];
    }
}
