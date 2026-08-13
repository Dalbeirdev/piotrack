<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property array<int, array<string, mixed>> $fields
 * @property array<string, mixed>|null $settings
 * @property string $slug
 * @property string $status
 * @property int $submission_count
 */
class Form extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id', 'name', 'slug', 'fields', 'settings',
        'target_list_id', 'lifecycle_stage', 'status', 'submission_count',
    ];

    protected function casts(): array
    {
        return [
            'fields' => 'array',
            'settings' => 'array',
        ];
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    /**
     * @return BelongsTo<MarketingList, $this>
     */
    public function targetList(): BelongsTo
    {
        return $this->belongsTo(MarketingList::class, 'target_list_id');
    }

    /**
     * @return HasMany<FormSubmission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class);
    }
}
