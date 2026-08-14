<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $body
 * @property bool $is_internal
 */
class TicketMessage extends Model
{
    use BelongsToTenant;

    protected $fillable = ['organization_id', 'ticket_id', 'user_id', 'body', 'is_internal'];

    protected function casts(): array
    {
        return ['is_internal' => 'boolean'];
    }
}
