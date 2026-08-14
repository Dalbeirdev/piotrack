<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An audited support-impersonation session (ADMIN-006). Platform-scoped: the
 * record belongs to the platform operator, not the impersonated tenant.
 *
 * @property int $id
 * @property int $impersonator_id
 * @property int $user_id
 * @property string $reason
 * @property Carbon $started_at
 * @property Carbon|null $ended_at
 */
class ImpersonationSession extends Model
{
    protected $fillable = ['impersonator_id', 'user_id', 'organization_id', 'reason', 'started_at', 'ended_at'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function impersonator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'impersonator_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
