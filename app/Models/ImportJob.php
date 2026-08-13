<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportJob extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id', 'user_id', 'resource', 'filename', 'status',
        'total', 'imported', 'skipped', 'failed', 'errors',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'integer',
            'imported' => 'integer',
            'skipped' => 'integer',
            'failed' => 'integer',
            'errors' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
