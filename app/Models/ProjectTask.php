<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property string $status
 * @property string $priority
 * @property Carbon|null $due_on
 */
class ProjectTask extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id', 'project_id', 'sprint_id', 'assignee_id',
        'title', 'description', 'status', 'priority', 'due_on',
    ];

    protected function casts(): array
    {
        return ['due_on' => 'date'];
    }
}
