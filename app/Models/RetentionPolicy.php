<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * How long a tenant keeps a class of records before it is pruned (PRIV-004).
 *
 * @property int $id
 * @property string $subject
 * @property int $retain_days
 * @property bool $is_active
 */
class RetentionPolicy extends Model
{
    use BelongsToTenant;

    /** Record classes a retention rule can govern. */
    public const SUBJECTS = ['audit_logs', 'ai_requests', 'outbound_messages', 'intent_signals', 'calls'];

    protected $fillable = ['organization_id', 'subject', 'retain_days', 'is_active'];

    protected function casts(): array
    {
        return ['retain_days' => 'integer', 'is_active' => 'boolean'];
    }
}
