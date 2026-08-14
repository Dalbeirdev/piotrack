<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A project deliverable with its client-approval state (PROJ-011/013,
 * PORTAL-006…009). Only `client_visible` deliverables reach the portal.
 *
 * @property int $id
 * @property string $title
 * @property string $type
 * @property string $status
 * @property string $approval_status
 * @property bool $client_visible
 * @property Carbon|null $approved_at
 * @property Carbon|null $due_on
 * @property string|null $rejection_reason
 */
class Deliverable extends Model
{
    use BelongsToTenant;

    public const APPROVAL_PENDING = 'pending';

    public const APPROVAL_APPROVED = 'approved';

    public const APPROVAL_REJECTED = 'rejected';

    protected $fillable = [
        'organization_id', 'project_id', 'file_id', 'approved_by', 'title', 'type', 'status',
        'approval_status', 'notes', 'rejection_reason', 'due_on', 'approved_at', 'client_visible',
    ];

    protected function casts(): array
    {
        return [
            'due_on' => 'date',
            'approved_at' => 'datetime',
            'client_visible' => 'boolean',
        ];
    }
}
