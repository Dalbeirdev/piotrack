<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A consulting or training engagement (TRAIN). The platform schedules, tracks
 * and reports on the session; the consulting itself is delivered by people.
 *
 * @property int $id
 * @property string $type
 * @property string|null $topic
 * @property string $status
 * @property Carbon|null $scheduled_at
 */
class Engagement extends Model
{
    use BelongsToTenant;

    public const TYPES = [
        'consulting', 'training', 'masterclass', 'workshop',
        'qbr', 'strategy_review', 'competitive_review', 'growth_planning',
    ];

    public const TOPICS = ['marketing', 'seo', 'sales', 'executive'];

    protected $fillable = [
        'organization_id', 'owner_id', 'type', 'topic', 'title',
        'scheduled_at', 'status', 'attendees', 'notes',
    ];

    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime', 'attendees' => 'integer'];
    }
}
