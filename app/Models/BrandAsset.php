<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $type
 * @property string $title
 */
class BrandAsset extends Model
{
    use BelongsToTenant;

    public const TYPES = [
        'logo', 'deck', 'one_pager', 'service_sheet', 'case_study_template',
        'proposal_template', 'social_proof', 'testimonial', 'guidelines', 'presentation',
    ];

    protected $fillable = ['organization_id', 'file_id', 'type', 'title', 'url', 'notes'];
}
