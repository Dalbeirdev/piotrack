<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<string, mixed>|null $rules
 * @property array<int, string>|null $platforms
 * @property string $source
 * @property bool $exclude_converted
 * @property int $member_count
 */
class RetargetingAudience extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id', 'name', 'source', 'marketing_list_id', 'rules',
        'platforms', 'exclude_converted', 'member_count',
    ];

    protected function casts(): array
    {
        return [
            'rules' => 'array',
            'platforms' => 'array',
            'exclude_converted' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<MarketingList, $this>
     */
    public function list(): BelongsTo
    {
        return $this->belongsTo(MarketingList::class, 'marketing_list_id');
    }
}
