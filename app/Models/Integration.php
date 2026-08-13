<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $last_synced_at
 * @property array<string, mixed>|null $credentials
 * @property array<string, mixed>|null $settings
 * @property array<int, string>|null $scopes
 * @property string $provider
 * @property string $status
 */
class Integration extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id', 'provider', 'name', 'status', 'credentials', 'scopes', 'settings',
        'last_synced_at', 'last_error',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = ['credentials'];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'scopes' => 'array',
            'settings' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }

    public function isConnected(): bool
    {
        return $this->status === 'connected';
    }

    /**
     * Derived health for display.
     */
    public function health(): string
    {
        return match ($this->status) {
            'connected' => 'healthy',
            'error' => 'degraded',
            default => 'disconnected',
        };
    }

    /**
     * @return HasMany<SyncRun, $this>
     */
    public function syncRuns(): HasMany
    {
        return $this->hasMany(SyncRun::class)->latest('id');
    }
}
