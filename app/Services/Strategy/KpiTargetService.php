<?php

namespace App\Services\Strategy;

use App\Models\KpiTarget;
use App\Services\Analytics\AnalyticsService;
use App\Services\Analytics\AttributionService;

/**
 * KPI targets vs actuals (STRAT-027…032, PERF-001…003). Targets are set by the
 * strategist; actuals come from the Stage 11 analytics layer, so attainment is
 * computed from real tenant data rather than self-reported.
 */
class KpiTargetService
{
    public function __construct(
        private AnalyticsService $analytics,
        private AttributionService $attribution,
    ) {}

    /**
     * The current actual for a metric.
     */
    public function actual(string $metric): int
    {
        $funnel = $this->analytics->funnel();

        return match ($metric) {
            'leads' => $funnel['leads'],
            'sqls' => $funnel['sqls'],
            'meetings' => $funnel['meetings'],
            'cpl' => $this->costPerLead($funnel['leads']),
            'mrr' => $this->analytics->revenue()['mrr'],
            'revenue' => $this->analytics->revenue()['contract_value'],
            'roi' => (int) round($this->attribution->marketingRoi() * 100), // basis: percent
            default => 0,
        };
    }

    /**
     * Attainment for every target, with the direction each metric improves in.
     *
     * @return list<array{id: int, metric: string, target: int, actual: int, attainment: float, on_track: bool, lower_is_better: bool}>
     */
    public function attainment(): array
    {
        return KpiTarget::orderBy('metric')->get()->map(function (KpiTarget $target) {
            $actual = $this->actual($target->metric);
            $lowerIsBetter = $target->metric === 'cpl';

            // Guard the divisor; a zero target is reported as 0% rather than error.
            $attainment = $target->target_value > 0
                ? round($actual / $target->target_value * 100, 2)
                : 0.0;

            return [
                'id' => $target->id,
                'metric' => $target->metric,
                'target' => $target->target_value,
                'actual' => $actual,
                'attainment' => $attainment,
                'on_track' => $lowerIsBetter
                    ? ($actual > 0 && $actual <= $target->target_value)
                    : $actual >= $target->target_value,
                'lower_is_better' => $lowerIsBetter,
            ];
        })->all();
    }

    private function costPerLead(int $leads): int
    {
        if ($leads === 0) {
            return 0;
        }

        $spend = $this->analytics->advertising()->spend;

        return (int) round($spend / $leads);
    }
}
