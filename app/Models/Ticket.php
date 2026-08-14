<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $subject
 * @property string $status
 * @property string $priority
 * @property Carbon|null $resolved_at
 */
class Ticket extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id', 'requester_id', 'assignee_id', 'subject', 'body',
        'status', 'priority', 'category', 'resolved_at',
    ];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    /**
     * @return HasMany<TicketMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class);
    }
}
