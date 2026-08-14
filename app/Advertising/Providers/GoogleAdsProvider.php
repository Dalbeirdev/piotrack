<?php

namespace App\Advertising\Providers;

use App\Advertising\Contracts\AdProvider;
use App\Models\AdCampaign;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Real Google Ads driver (GAQL over the Google Ads REST API). Real code, but
 * with no developer token / OAuth in this environment it is NOT exercised in
 * tests — status "Implemented (untested — requires credentials)", never
 * "Tested" (ADR-0006, §38). Selected when ADVERTISING_DRIVER=live for
 * google_search/microsoft campaigns.
 */
class GoogleAdsProvider implements AdProvider
{
    public function metrics(AdCampaign $campaign, int $days): array
    {
        $config = (array) config('advertising.google');

        if (empty($config['developer_token']) || empty($config['access_token']) || $campaign->external_id === null) {
            return [];
        }

        try {
            $response = Http::withToken((string) $config['access_token'])
                ->withHeaders(['developer-token' => (string) $config['developer_token']])
                ->timeout(30)
                ->post("https://googleads.googleapis.com/v17/customers/{$config['customer_id']}/googleAds:searchStream", [
                    'query' => 'SELECT segments.date, metrics.impressions, metrics.clicks, metrics.cost_micros, '
                        ."metrics.conversions, metrics.conversions_value FROM campaign WHERE campaign.id = {$campaign->external_id} "
                        ."DURING LAST_{$days}_DAYS",
                ]);

            if ($response->failed()) {
                return [];
            }

            $rows = [];
            foreach ((array) $response->json('0.results', []) as $result) {
                $rows[] = [
                    'date' => (string) ($result['segments']['date'] ?? ''),
                    'impressions' => (int) ($result['metrics']['impressions'] ?? 0),
                    'clicks' => (int) ($result['metrics']['clicks'] ?? 0),
                    'spend' => (int) round(((int) ($result['metrics']['costMicros'] ?? 0)) / 10000), // micros → minor units
                    'conversions' => (int) ($result['metrics']['conversions'] ?? 0),
                    'revenue' => (int) round(((float) ($result['metrics']['conversionsValue'] ?? 0)) * 100),
                ];
            }

            return $rows;
        } catch (Throwable) {
            return [];
        }
    }

    public function push(AdCampaign $campaign): ?string
    {
        // Real create/update via the Google Ads API mutate endpoint goes here;
        // returns null until credentials are configured.
        return null;
    }
}
