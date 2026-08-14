<?php

namespace App\Advertising\Providers;

use App\Advertising\Contracts\AdProvider;
use App\Models\AdCampaign;
use Illuminate\Support\Carbon;

/**
 * The default, fully-tested ad driver: deterministic daily metrics derived from
 * a hash of the campaign + date, scaled to the campaign's daily budget. The
 * whole pipeline — snapshots, KPI rollups, budget pacing — runs for real
 * against it (ADR-0006). Also a legitimate "bring-your-own-numbers" mode.
 */
class FixtureAdProvider implements AdProvider
{
    public function metrics(AdCampaign $campaign, int $days): array
    {
        $rows = [];
        $budget = max(1000, $campaign->daily_budget); // at least $10/day of modeled spend

        for ($i = 0; $i < $days; $i++) {
            $date = Carbon::today()->subDays($i)->toDateString();
            $seed = crc32($campaign->id.'|'.$date);

            $impressions = 500 + $seed % 4500;
            $ctr = 0.01 + ($seed % 40) / 1000;                 // 1–5%
            $clicks = (int) round($impressions * $ctr);
            $cpc = 50 + $seed % 200;                            // $0.50–$2.50
            $spend = (int) min($budget, $clicks * $cpc);
            $convRate = 0.02 + ($seed % 80) / 1000;            // 2–10%
            $conversions = (int) round($clicks * $convRate);
            $revenue = $conversions * (20000 + $seed % 30000); // $200–$500 per conversion

            $rows[] = compact('date', 'impressions', 'clicks', 'spend', 'conversions', 'revenue');
        }

        return $rows;
    }

    public function push(AdCampaign $campaign): ?string
    {
        // Fixture mode does not talk to a platform.
        return 'fixture-'.$campaign->id;
    }
}
