<?php

namespace App\Models;

use App\Authorization\Role;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'owner_id',
    ];

    protected static function booted(): void
    {
        static::creating(function (Organization $organization) {
            if (empty($organization->slug)) {
                $organization->slug = self::uniqueSlug($organization->name);
            }
        });
    }

    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'organization';
        $slug = $base;
        $i = 1;

        while (self::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsToMany<User, $this, OrganizationUser>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(OrganizationUser::class)
            ->withPivot(['role', 'status', 'joined_at'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<Team, $this>
     */
    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    /**
     * @return HasMany<Invitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    /**
     * Active memberships holding the Owner role.
     *
     * @return BelongsToMany<User, $this, OrganizationUser>
     */
    public function owners(): BelongsToMany
    {
        return $this->members()
            ->wherePivot('role', Role::Owner->value)
            ->wherePivot('status', 'active');
    }
}
