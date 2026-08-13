<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanEntitlement extends Model
{
    protected $fillable = [
        'plan_id', 'key', 'kind', 'bool_value', 'int_value',
    ];

    protected function casts(): array
    {
        return [
            'bool_value' => 'boolean',
            'int_value' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
