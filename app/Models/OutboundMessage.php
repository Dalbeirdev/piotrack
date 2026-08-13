<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $sent_at
 * @property Carbon|null $opened_at
 * @property Carbon|null $clicked_at
 * @property string $channel
 * @property string $status
 * @property string $token
 */
class OutboundMessage extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id', 'contact_id', 'channel', 'address', 'subject', 'body',
        'token', 'status', 'source', 'workflow_id', 'provider_message_id',
        'sent_at', 'opened_at', 'clicked_at', 'error',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'opened_at' => 'datetime',
            'clicked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
