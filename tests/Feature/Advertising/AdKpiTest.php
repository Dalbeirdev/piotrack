<?php

use App\Advertising\AdKpi;

it('computes advertising KPIs from raw counts', function () {
    // 10,000 impressions, 200 clicks, $400 spend, 20 conversions, $2,000 revenue.
    $kpi = AdKpi::from(10000, 200, 40000, 20, 200000);

    expect($kpi->ctr)->toBe(0.02)              // 200 / 10000
        ->and($kpi->cpc)->toBe(200.0)          // 40000 / 200 = 200 cents ($2.00)
        ->and($kpi->cpa)->toBe(2000.0)         // 40000 / 20
        ->and($kpi->roas)->toBe(5.0)           // 200000 / 40000
        ->and($kpi->conversionRate)->toBe(0.1); // 20 / 200

    $array = $kpi->toArray();
    expect($array['ctr'])->toBe(2.0)           // percentage
        ->and($array['roas'])->toBe(5.0)
        ->and($array['cpc'])->toBe(200)
        ->and($array['conversion_rate'])->toBe(10.0);
});

it('guards against divide-by-zero', function () {
    $kpi = AdKpi::from(0, 0, 0, 0, 0);

    expect($kpi->ctr)->toBe(0.0)
        ->and($kpi->cpc)->toBe(0.0)
        ->and($kpi->cpa)->toBe(0.0)
        ->and($kpi->roas)->toBe(0.0)
        ->and($kpi->conversionRate)->toBe(0.0);
});
