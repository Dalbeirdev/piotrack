<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $channel
 * @property string $status
 * @property string|null $summary
 * @property Carbon|null $started_at
 */
class AiConversation extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id', 'contact_id', 'channel', 'status', 'summary', 'started_at',
    ];

    protected function casts(): array
    {
        return ['started_at' => 'datetime'];
    }

    /**
     * @return HasMany<AiMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(AiMessage::class);
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
