<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property int $records
 * @property string $status
 */
class SyncRun extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id', 'integration_id', 'status', 'started_at', 'finished_at', 'records', 'error',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'records' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Integration, $this>
     */
    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }
}
