<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only audit record. Never updated or soft-deleted; retention is
 * handled by scheduled pruning per the privacy retention policy (PRIV).
 */
class AuditLog extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'organization_id',
        'actor_id',
        'action',
        'resource_type',
        'resource_id',
        'context',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'context' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
