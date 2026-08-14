<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A lead-guarantee / performance agreement (PERF).
 *
 * @property int $id
 * @property string $name
 * @property string $model
 * @property int $lead_target
 * @property int $sql_target
 * @property int $meeting_target
 * @property array<string, mixed>|null $quality_criteria
 * @property int $sla_days
 * @property string $status
 * @property array<int, string>|null $deliverables
 * @property Carbon|null $period_start
 * @property Carbon|null $period_end
 */
class PerformanceAgreement extends Model
{
    use BelongsToTenant;

    public const MODELS = ['guarantee', 'performance_pricing', 'pay_per_lead'];

    protected $fillable = [
        'organization_id', 'name', 'model', 'lead_target', 'sql_target', 'meeting_target',
        'quality_criteria', 'deliverables', 'sla_days', 'period_start', 'period_end', 'status',
    ];

    protected function casts(): array
    {
        return [
            'lead_target' => 'integer',
            'sql_target' => 'integer',
            'meeting_target' => 'integer',
            'sla_days' => 'integer',
            'quality_criteria' => 'array',
            'deliverables' => 'array',
            'period_start' => 'date',
            'period_end' => 'date',
        ];
    }
}
