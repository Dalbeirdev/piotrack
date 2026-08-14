<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * One recorded model call: token accounting, estimated cost and outcome, used
 * for per-tenant/user/feature cost attribution (AIPF-003).
 *
 * @property int $id
 * @property string $feature
 * @property string $provider
 * @property string $model
 * @property int $prompt_tokens
 * @property int $completion_tokens
 * @property int $estimated_cost
 * @property int $duration_ms
 * @property int $attempts
 * @property string $status
 * @property string|null $error
 */
class AiRequest extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id', 'user_id', 'ai_prompt_template_id', 'feature', 'provider', 'model',
        'prompt_tokens', 'completion_tokens', 'estimated_cost', 'duration_ms', 'attempts', 'status', 'error',
    ];

    protected function casts(): array
    {
        return [
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'estimated_cost' => 'integer',
            'duration_ms' => 'integer',
            'attempts' => 'integer',
        ];
    }
}
