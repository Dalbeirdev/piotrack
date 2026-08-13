<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $slug
 * @property string $status
 * @property int $view_count
 */
class LandingPage extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id', 'name', 'slug', 'headline', 'subheadline',
        'body_html', 'form_id', 'status', 'view_count',
    ];

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    /**
     * @return BelongsTo<Form, $this>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }
}
