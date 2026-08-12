<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * The organization membership pivot. Carries the member's role and status
 * within a specific organization (ADR-0002).
 */
class OrganizationUser extends Pivot
{
    protected $table = 'organization_user';

    public $incrementing = true;

    protected $fillable = [
        'organization_id',
        'user_id',
        'role',
        'status',
        'joined_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
        ];
    }
}
