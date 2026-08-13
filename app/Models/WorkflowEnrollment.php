<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $next_run_at
 * @property Carbon|null $enrolled_at
 * @property Carbon|null $completed_at
 * @property int $current_position
 * @property string $status
 */
class WorkflowEnrollment extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id', 'workflow_id', 'contact_id', 'current_position',
        'status', 'next_run_at', 'enrolled_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'next_run_at' => 'datetime',
            'enrolled_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Workflow, $this>
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
