<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * @property array<int, array<string, mixed>>|null $steps
 */
class SalesPlay extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id', 'name', 'description', 'steps', 'target_segment',
    ];

    protected function casts(): array
    {
        return ['steps' => 'array'];
    }
}
