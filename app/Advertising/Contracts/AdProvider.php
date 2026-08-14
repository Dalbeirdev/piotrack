<?php

namespace App\Advertising\Contracts;

use App\Models\AdCampaign;

/**
 * Ad-platform data source (ADR-0006). The `fixture` driver is the tested
 * default; the live per-platform drivers are real but untested here (no
 * credentials).
 */
interface AdProvider
{
    /**
     * Daily performance for the last N days.
     *
     * @return list<array{date: string, impressions: int, clicks: int, spend: int, conversions: int, revenue: int}>
     */
    public function metrics(AdCampaign $campaign, int $days): array;

    /**
     * Create/update the campaign on the platform, returning its external id
     * (null when the platform is not configured).
     */
    public function push(AdCampaign $campaign): ?string;
}
