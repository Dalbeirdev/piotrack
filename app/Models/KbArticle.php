<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A help-centre article (SUPP-001). Product-wide, not tenant-scoped.
 *
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property bool $is_published
 * @property int $views
 */
class KbArticle extends Model
{
    protected $fillable = ['title', 'slug', 'category', 'excerpt', 'body', 'is_published', 'views'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'views' => 'integer'];
    }
}
