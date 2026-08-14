<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A delivery-team assignment on a project (PROJ-001…008).
 *
 * @property int $id
 * @property string $role
 */
class ProjectMember extends Model
{
    use BelongsToTenant;

    /** The delivery roles an engagement staffs. */
    public const ROLES = [
        'strategist', 'project_manager', 'seo', 'ppc', 'developer', 'designer', 'copywriter', 'automation',
    ];

    protected $fillable = ['organization_id', 'project_id', 'user_id', 'role'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
