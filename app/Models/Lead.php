<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Contracts\HasActivities;
use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $converted_at
 */
class Lead extends Model implements HasActivities
{
    /** @use HasFactory<LeadFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    public const STATUSES = ['new', 'working', 'qualified', 'unqualified', 'converted'];

    protected $fillable = [
        'organization_id', 'first_name', 'last_name', 'email', 'phone', 'company_name',
        'source', 'campaign', 'status', 'owner_id', 'converted_contact_id', 'converted_deal_id', 'converted_at',
    ];

    protected function casts(): array
    {
        return ['converted_at' => 'datetime'];
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function isConverted(): bool
    {
        return $this->status === 'converted';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return MorphMany<Activity, $this>
     */
    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }
}
