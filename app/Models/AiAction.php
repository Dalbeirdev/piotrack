<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A sensitive action the AI has PROPOSED but may not perform on its own
 * (AIPF-006). It only leaves `pending` when a human with `ai.actions.approve`
 * confirms or rejects it.
 *
 * @property int $id
 * @property string $type
 * @property string $summary
 * @property array<string, mixed> $payload
 * @property string $status
 * @property int|null $proposed_by
 * @property int|null $confirmed_by
 * @property Carbon|null $confirmed_at
 * @property Carbon|null $rejected_at
 * @property Carbon|null $executed_at
 * @property string|null $result
 */
class AiAction extends Model
{
    use BelongsToTenant;

    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_EXECUTED = 'executed';

    protected $fillable = [
        'organization_id', 'type', 'summary', 'payload', 'subject_type', 'subject_id',
        'status', 'proposed_by', 'confirmed_by', 'confirmed_at', 'rejected_at', 'executed_at', 'result',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'confirmed_at' => 'datetime',
            'rejected_at' => 'datetime',
            'executed_at' => 'datetime',
        ];
    }

    /**
     * Whether this action type may never be executed without confirmation.
     */
    public function isSensitive(): bool
    {
        /** @var list<string> $sensitive */
        $sensitive = config('ai.sensitive_actions', []);

        return in_array($this->type, $sensitive, true);
    }
}
