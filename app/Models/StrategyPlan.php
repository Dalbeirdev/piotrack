<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $status
 * @property Carbon|null $period_start
 * @property Carbon|null $period_end
 */
class StrategyPlan extends Model
{
    use BelongsToTenant;

    protected $fillable = ['organization_id', 'name', 'summary', 'status', 'period_start', 'period_end'];

    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date'];
    }

    /**
     * @return HasMany<StrategyItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(StrategyItem::class);
    }
}
