<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $scheduled_at
 * @property Carbon|null $published_at
 * @property string $channel
 * @property string $status
 * @property int $impressions
 * @property int $likes
 * @property int $comments
 * @property int $shares
 */
class SocialPost extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id', 'channel', 'type', 'body', 'media_url', 'content_piece_id',
        'status', 'scheduled_at', 'published_at', 'external_id',
        'impressions', 'likes', 'comments', 'shares',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    /**
     * @return BelongsTo<ContentPiece, $this>
     */
    public function contentPiece(): BelongsTo
    {
        return $this->belongsTo(ContentPiece::class);
    }
}
