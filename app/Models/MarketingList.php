<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property array<string, mixed>|null $criteria
 * @property int $member_count
 * @property string $type
 */
class MarketingList extends Model
{
    use BelongsToTenant;

    protected $table = 'marketing_lists';

    protected $fillable = [
        'organization_id', 'name', 'description', 'type', 'criteria', 'member_count',
    ];

    protected function casts(): array
    {
        return ['criteria' => 'array'];
    }

    /**
     * @return HasMany<ListMembership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(ListMembership::class);
    }

    /**
     * @return BelongsToMany<Contact, $this>
     */
    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'list_memberships', 'marketing_list_id', 'contact_id');
    }
}
