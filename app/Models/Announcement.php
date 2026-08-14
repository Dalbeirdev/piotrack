<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property string $audience
 * @property string $type
 * @property Carbon|null $published_at
 */
class Announcement extends Model
{
    protected $fillable = ['title', 'body', 'audience', 'type', 'published_at'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }
}
