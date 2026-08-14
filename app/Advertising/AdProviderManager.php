<?php

namespace App\Advertising;

use App\Advertising\Contracts\AdProvider;
use App\Advertising\Providers\FixtureAdProvider;
use App\Advertising\Providers\GoogleAdsProvider;
use App\Advertising\Providers\LinkedInAdsProvider;
use App\Advertising\Providers\MetaAdsProvider;
use App\Models\AdCampaign;

/**
 * Resolves the ad driver for a campaign (ADR-0006). In the default `fixture`
 * mode every platform uses the tested fixture driver; in `live` mode the driver
 * is chosen per campaign platform.
 */
class AdProviderManager
{
    public function for(AdCampaign $campaign): AdProvider
    {
        if ((string) config('advertising.driver', 'fixture') === 'fixture') {
            return new FixtureAdProvider;
        }

        return match ($campaign->platform) {
            'google_search', 'microsoft', 'youtube' => new GoogleAdsProvider,
            'meta' => new MetaAdsProvider,
            'linkedin' => new LinkedInAdsProvider,
            default => new FixtureAdProvider,
        };
    }
}
