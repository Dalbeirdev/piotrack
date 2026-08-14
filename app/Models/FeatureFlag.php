<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A platform-level feature flag (ADMIN-004). Not tenant-scoped: flags belong to
 * the platform operator and are resolved PER organization by FeatureFlagService.
 *
 * @property int $id
 * @property string $key
 * @property bool $is_enabled
 * @property bool $is_kill_switch
 * @property array{organizations?: list<int>, percentage?: int}|null $rollout
 */
class FeatureFlag extends Model
{
    protected $fillable = ['key', 'description', 'is_enabled', 'is_kill_switch', 'rollout'];

    protected function casts(): array
    {
        return ['is_enabled' => 'boolean', 'is_kill_switch' => 'boolean', 'rollout' => 'array'];
    }
}
