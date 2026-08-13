<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $added_at
 */
class ListMembership extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id', 'marketing_list_id', 'contact_id', 'added_at',
    ];

    protected function casts(): array
    {
        return ['added_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<MarketingList, $this>
     */
    public function list(): BelongsTo
    {
        return $this->belongsTo(MarketingList::class, 'marketing_list_id');
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
