<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * A monitored prompt in the AI-visibility library (AIVIS-015), optionally scoped
 * to a service, city or vertical for dimension-level visibility.
 *
 * @property int $id
 * @property string $text
 * @property string|null $service
 * @property string|null $city
 * @property string|null $vertical
 * @property bool $is_active
 */
class AiPrompt extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id', 'text', 'category', 'service', 'city', 'vertical', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
