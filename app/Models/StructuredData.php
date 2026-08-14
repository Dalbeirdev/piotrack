<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $schema_type
 * @property string $jsonld
 * @property bool $is_applied
 */
class StructuredData extends Model
{
    use BelongsToTenant;

    protected $table = 'structured_data';

    protected $fillable = [
        'organization_id', 'url', 'schema_type', 'jsonld', 'is_applied',
    ];

    protected function casts(): array
    {
        return ['is_applied' => 'boolean'];
    }
}
