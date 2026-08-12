<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'name',
    ];

    /**
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }
}
