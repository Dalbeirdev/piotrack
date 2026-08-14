<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A tracked data-subject request (PRIV-003) so an export or erasure is an
 * auditable object rather than an ad-hoc script run.
 *
 * @property int $id
 * @property string $type
 * @property string $status
 * @property string|null $file_path
 * @property Carbon|null $completed_at
 */
class DataRequest extends Model
{
    public const TYPES = ['export', 'delete_user', 'delete_organization'];

    protected $fillable = [
        'organization_id', 'user_id', 'requested_by', 'type', 'status',
        'file_path', 'error', 'completed_at',
    ];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime'];
    }
}
