<?php

namespace App\Advertising\Providers;

use App\Advertising\Contracts\AdProvider;
use App\Models\AdCampaign;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Real LinkedIn Ads driver (Marketing API adAnalytics). Real code, untested
 * here (no access token) — status "Implemented (untested — requires
 * credentials)" (ADR-0006, §38). Selected when ADVERTISING_DRIVER=live for
 * linkedin campaigns.
 */
class LinkedInAdsProvider implements AdProvider
{
    public function metrics(AdCampaign $campaign, int $days): array
    {
        $token = (string) config('advertising.linkedin.access_token');

        if ($token === '' || $campaign->external_id === null) {
            return [];
        }

        try {
            $response = Http::withToken($token)->timeout(30)->get('https://api.linkedin.com/rest/adAnalytics', [
                'q' => 'analytics',
                'pivot' => 'CAMPAIGN',
                'timeGranularity' => 'DAILY',
                'campaigns' => "urn:li:sponsoredCampaign:{$campaign->external_id}",
                'fields' => 'impressions,clicks,costInLocalCurrency,externalWebsiteConversions',
            ]);

            if ($response->failed()) {
                return [];
            }

            $rows = [];
            foreach ((array) $response->json('elements', []) as $element) {
                $rows[] = [
                    'date' => (string) ($element['dateRange']['start'] ?? ''),
                    'impressions' => (int) ($element['impressions'] ?? 0),
                    'clicks' => (int) ($element['clicks'] ?? 0),
                    'spend' => (int) round(((float) ($element['costInLocalCurrency'] ?? 0)) * 100),
                    'conversions' => (int) ($element['externalWebsiteConversions'] ?? 0),
                    'revenue' => 0,
                ];
            }

            return $rows;
        } catch (Throwable) {
            return [];
        }
    }

    public function push(AdCampaign $campaign): ?string
    {
        return null;
    }
}
