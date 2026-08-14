<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $policy
 * @property string $version
 * @property Carbon $accepted_at
 */
class PolicyAcceptance extends Model
{
    public const POLICIES = ['terms', 'privacy', 'dpa'];

    protected $fillable = ['user_id', 'policy', 'version', 'ip_address', 'accepted_at'];

    protected function casts(): array
    {
        return ['accepted_at' => 'datetime'];
    }
}
