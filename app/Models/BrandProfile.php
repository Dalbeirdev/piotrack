<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * The organization's brand positioning, messaging and visual direction — one row
 * per organization. The platform stores and versions these decisions; arriving
 * at them, and producing the creative, is human work.
 *
 * @property int $id
 * @property string|null $positioning_statement
 * @property string|null $tagline
 */
class BrandProfile extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id', 'positioning_statement', 'usp', 'value_proposition', 'differentiators',
        'narrative', 'story', 'tone_of_voice', 'messaging_hierarchy', 'elevator_pitch', 'tagline',
        'palette', 'typography', 'imagery_direction', 'guidelines_url',
    ];

    protected function casts(): array
    {
        return [
            'differentiators' => 'array',
            'messaging_hierarchy' => 'array',
            'palette' => 'array',
            'typography' => 'array',
        ];
    }
}
