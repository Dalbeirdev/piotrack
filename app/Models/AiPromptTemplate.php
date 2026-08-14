<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $key
 * @property int $version
 * @property string|null $system
 * @property string $template
 * @property bool $is_active
 */
class AiPromptTemplate extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id', 'key', 'version', 'description', 'system', 'template', 'is_active',
    ];

    protected function casts(): array
    {
        return ['version' => 'integer', 'is_active' => 'boolean'];
    }
}
