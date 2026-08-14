<?php

namespace App\Advertising\Providers;

use App\Advertising\Contracts\AdProvider;
use App\Models\AdCampaign;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Real Meta/Facebook Ads driver (Graph API Insights). Real code, untested here
 * (no access token) — status "Implemented (untested — requires credentials)"
 * (ADR-0006, §38). Selected when ADVERTISING_DRIVER=live for meta campaigns.
 */
class MetaAdsProvider implements AdProvider
{
    public function metrics(AdCampaign $campaign, int $days): array
    {
        $token = (string) config('advertising.meta.access_token');

        if ($token === '' || $campaign->external_id === null) {
            return [];
        }

        try {
            $response = Http::timeout(30)->get("https://graph.facebook.com/v20.0/{$campaign->external_id}/insights", [
                'access_token' => $token,
                'time_increment' => 1,
                'date_preset' => 'last_'.$days.'d',
                'fields' => 'impressions,clicks,spend,actions,action_values',
            ]);

            if ($response->failed()) {
                return [];
            }

            $rows = [];
            foreach ((array) $response->json('data', []) as $day) {
                $rows[] = [
                    'date' => (string) ($day['date_start'] ?? ''),
                    'impressions' => (int) ($day['impressions'] ?? 0),
                    'clicks' => (int) ($day['clicks'] ?? 0),
                    'spend' => (int) round(((float) ($day['spend'] ?? 0)) * 100),
                    'conversions' => 0,
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
