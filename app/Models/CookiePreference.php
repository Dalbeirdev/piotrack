<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property bool $necessary
 * @property bool $analytics
 * @property bool $marketing
 * @property Carbon $decided_at
 */
class CookiePreference extends Model
{
    protected $fillable = [
        'user_id', 'visitor_token', 'necessary', 'analytics', 'marketing', 'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'necessary' => 'boolean',
            'analytics' => 'boolean',
            'marketing' => 'boolean',
            'decided_at' => 'datetime',
        ];
    }
}
