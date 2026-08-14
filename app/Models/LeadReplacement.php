<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A lead replaced under a performance agreement's quality guarantee (PERF-008).
 *
 * @property int $id
 * @property string $reason
 * @property Carbon|null $replaced_at
 */
class LeadReplacement extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id', 'performance_agreement_id', 'contact_id',
        'replacement_contact_id', 'reason', 'replaced_at',
    ];

    protected function casts(): array
    {
        return ['replaced_at' => 'datetime'];
    }
}
