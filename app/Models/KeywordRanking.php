<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $checked_at
 * @property int|null $position
 * @property bool $is_competitor
 */
class KeywordRanking extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id', 'keyword_id', 'engine', 'location', 'position', 'url',
        'provider', 'is_competitor', 'competitor_domain', 'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'checked_at' => 'datetime',
            'is_competitor' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Keyword, $this>
     */
    public function keyword(): BelongsTo
    {
        return $this->belongsTo(Keyword::class);
    }
}
