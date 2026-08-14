<?php

namespace App\Advertising;

/**
 * Derived advertising KPIs computed from raw counts (pure). Spend/revenue/CPC/
 * CPA are in minor units; CTR/ROAS/conversion-rate are ratios. Every divisor is
 * guarded so a zero denominator yields 0 rather than a division error.
 */
final class AdKpi
{
    public function __construct(
        public int $impressions,
        public int $clicks,
        public int $spend,
        public int $conversions,
        public int $revenue,
        public float $ctr,
        public float $cpc,
        public float $cpa,
        public float $roas,
        public float $conversionRate,
    ) {}

    public static function from(int $impressions, int $clicks, int $spend, int $conversions, int $revenue): self
    {
        return new self(
            impressions: $impressions,
            clicks: $clicks,
            spend: $spend,
            conversions: $conversions,
            revenue: $revenue,
            ctr: $impressions > 0 ? $clicks / $impressions : 0.0,
            cpc: $clicks > 0 ? $spend / $clicks : 0.0,
            cpa: $conversions > 0 ? $spend / $conversions : 0.0,
            roas: $spend > 0 ? $revenue / $spend : 0.0,
            conversionRate: $clicks > 0 ? $conversions / $clicks : 0.0,
        );
    }

    /**
     * Display-ready array (ratios as rounded values; money left in minor units).
     *
     * @return array<string, int|float>
     */
    public function toArray(): array
    {
        return [
            'impressions' => $this->impressions,
            'clicks' => $this->clicks,
            'spend' => $this->spend,
            'conversions' => $this->conversions,
            'revenue' => $this->revenue,
            'ctr' => round($this->ctr * 100, 2),          // percentage
            'cpc' => (int) round($this->cpc),             // minor units
            'cpa' => (int) round($this->cpa),             // minor units (cost per acquisition/lead)
            'roas' => round($this->roas, 2),              // ratio
            'conversion_rate' => round($this->conversionRate * 100, 2),
        ];
    }
}
