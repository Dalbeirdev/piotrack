<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A marketing KPI target for a period (STRAT-027…032, PERF-001…003). Money
 * metrics are stored in minor units.
 *
 * @property int $id
 * @property string $metric
 * @property int $target_value
 * @property Carbon|null $period_start
 * @property Carbon|null $period_end
 */
class KpiTarget extends Model
{
    use BelongsToTenant;

    public const METRICS = ['leads', 'sqls', 'meetings', 'cpl', 'mrr', 'revenue', 'roi'];

    protected $fillable = ['organization_id', 'metric', 'target_value', 'period_start', 'period_end'];

    protected function casts(): array
    {
        return ['target_value' => 'integer', 'period_start' => 'date', 'period_end' => 'date'];
    }
}
