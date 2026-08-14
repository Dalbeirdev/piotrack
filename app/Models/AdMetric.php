<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $date
 * @property int $impressions
 * @property int $clicks
 * @property int $spend
 * @property int $conversions
 * @property int $revenue
 */
class AdMetric extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id', 'ad_campaign_id', 'date', 'impressions', 'clicks', 'spend', 'conversions', 'revenue',
    ];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    /**
     * @return BelongsTo<AdCampaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AdCampaign::class, 'ad_campaign_id');
    }
}
