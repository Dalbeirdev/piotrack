<?php

namespace App\Services\Advertising;

use App\Advertising\AdProviderManager;
use App\Models\AdCampaign;
use App\Support\AuditLogger;
use Illuminate\Validation\ValidationException;

/**
 * Campaign lifecycle (PPC/LIAD/META structure). Create/update/status + push to
 * the platform via the AdProvider (fixture in dev/tests; live drivers untested).
 */
class AdCampaignService
{
    private const STATUSES = ['draft', 'active', 'paused', 'ended'];

    public function __construct(
        private AdProviderManager $providers,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): AdCampaign
    {
        $campaign = AdCampaign::create($data);

        $this->audit->log('ads.campaign.created', context: ['name' => $campaign->name, 'platform' => $campaign->platform], resourceType: 'ad_campaign', resourceId: (string) $campaign->id, organizationId: $campaign->organization_id);

        return $campaign;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(AdCampaign $campaign, array $data): AdCampaign
    {
        $campaign->update($data);
        $this->audit->log('ads.campaign.updated', resourceType: 'ad_campaign', resourceId: (string) $campaign->id, organizationId: $campaign->organization_id);

        return $campaign;
    }

    public function setStatus(AdCampaign $campaign, string $status): AdCampaign
    {
        if (! in_array($status, self::STATUSES, true)) {
            throw ValidationException::withMessages(['status' => __('Invalid campaign status.')]);
        }

        // Soft rule: a campaign needs at least one ad group + ad to go live.
        if ($status === 'active' && ! $campaign->groups()->whereHas('ads')->exists()) {
            throw ValidationException::withMessages(['status' => __('Add an ad group with at least one ad before activating.')]);
        }

        $campaign->update(['status' => $status]);
        $this->audit->log('ads.campaign.status_changed', context: ['status' => $status], resourceType: 'ad_campaign', resourceId: (string) $campaign->id, organizationId: $campaign->organization_id);

        return $campaign;
    }

    /**
     * Push the campaign to its platform, storing the external id.
     */
    public function sync(AdCampaign $campaign): AdCampaign
    {
        $externalId = $this->providers->for($campaign)->push($campaign);

        if ($externalId !== null) {
            $campaign->update(['external_id' => $externalId]);
        }

        return $campaign;
    }
}
