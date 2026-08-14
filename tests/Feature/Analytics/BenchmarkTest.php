<?php

use App\Models\Contact;
use App\Models\Deal;
use App\Models\Organization;
use App\Services\Analytics\BenchmarkService;
use App\Support\CurrentOrganization;

/** Seed one org with a given number of leads and SQLs. */
function seedFunnel(Organization $org, int $leads, int $sqls): void
{
    app(CurrentOrganization::class)->set($org);
    for ($i = 0; $i < $leads; $i++) {
        Contact::create([
            'first_name' => 'L'.$i,
            'email' => "l{$i}-{$org->id}@x.com",
            'lifecycle_stage' => $i < $sqls ? 'sql' : 'lead',
        ]);
    }
    app(CurrentOrganization::class)->forget();
}

it('suppresses a benchmark below the k-anonymity cohort floor', function () {
    config()->set('analytics.benchmark_min_cohort', 3);

    [$orgA] = analyticsOrganization('A');
    [$orgB] = analyticsOrganization('B');
    seedFunnel($orgA, 10, 5);
    seedFunnel($orgB, 10, 2);

    app(CurrentOrganization::class)->set($orgA);
    $result = app(BenchmarkService::class)->benchmark('lead_to_sql');
    app(CurrentOrganization::class)->forget();

    expect($result)->toBeNull(); // only 2 contributing orgs < floor of 3
});

it('returns an anonymized aggregate once the cohort is large enough', function () {
    config()->set('analytics.benchmark_min_cohort', 3);

    [$orgA] = analyticsOrganization('A');
    [$orgB] = analyticsOrganization('B');
    [$orgC] = analyticsOrganization('C');
    seedFunnel($orgA, 10, 1);  // 10%
    seedFunnel($orgB, 10, 5);  // 50%
    seedFunnel($orgC, 10, 9);  // 90%

    app(CurrentOrganization::class)->set($orgB);
    $result = app(BenchmarkService::class)->benchmark('lead_to_sql');
    app(CurrentOrganization::class)->forget();

    expect($result)->not->toBeNull()
        ->and($result['cohort'])->toBe(3)
        ->and($result['peer_median'])->toBe(50.0)
        ->and($result['your_value'])->toBe(50.0)
        ->and($result['top_quartile'])->toBe(70.0);
});

it('reports the requesting tenant percentile without exposing peer values', function () {
    config()->set('analytics.benchmark_min_cohort', 3);

    [$orgA] = analyticsOrganization('A');
    [$orgB] = analyticsOrganization('B');
    [$orgC] = analyticsOrganization('C');
    seedFunnel($orgA, 10, 1);
    seedFunnel($orgB, 10, 5);
    seedFunnel($orgC, 10, 9);

    app(CurrentOrganization::class)->set($orgC); // the best performer
    $result = app(BenchmarkService::class)->benchmark('lead_to_sql');
    app(CurrentOrganization::class)->forget();

    expect($result['your_percentile'])->toBe(100)
        // Only aggregates are exposed — no per-organization values or identifiers.
        ->and(array_keys($result))->toBe(['metric', 'cohort', 'peer_median', 'top_quartile', 'peer_average', 'your_value', 'your_percentile']);
});

it('omits every suppressed metric from the full benchmark set', function () {
    config()->set('analytics.benchmark_min_cohort', 5); // higher than the number of orgs

    [$orgA] = analyticsOrganization('A');
    [$orgB] = analyticsOrganization('B');
    seedFunnel($orgA, 5, 1);
    seedFunnel($orgB, 5, 2);

    app(CurrentOrganization::class)->set($orgA);
    $all = app(BenchmarkService::class)->all();
    app(CurrentOrganization::class)->forget();

    expect($all)->toBe([]);
});

it('excludes organizations with no data from the cohort', function () {
    config()->set('analytics.benchmark_min_cohort', 3);

    [$orgA] = analyticsOrganization('A');
    [$orgB] = analyticsOrganization('B');
    [$orgC] = analyticsOrganization('C');
    analyticsOrganization('D'); // no contacts: must not count toward the cohort
    seedFunnel($orgA, 10, 1);
    seedFunnel($orgB, 10, 5);
    seedFunnel($orgC, 10, 9);

    app(CurrentOrganization::class)->set($orgA);
    $result = app(BenchmarkService::class)->benchmark('lead_to_sql');
    app(CurrentOrganization::class)->forget();

    expect($result['cohort'])->toBe(3);
});

it('benchmarks average MRR across tenants', function () {
    config()->set('analytics.benchmark_min_cohort', 3);

    $orgs = [];
    foreach (['A', 'B', 'C'] as $i => $name) {
        [$org] = analyticsOrganization($name);
        $orgs[] = $org;
        app(CurrentOrganization::class)->set($org);
        $deal = Deal::factory()->create(['organization_id' => $org->id, 'mrr' => ($i + 1) * 10000]);
        placeDealInStage($deal, 'is_won');
        app(CurrentOrganization::class)->forget();
    }

    app(CurrentOrganization::class)->set($orgs[1]);
    $result = app(BenchmarkService::class)->benchmark('avg_mrr');
    app(CurrentOrganization::class)->forget();

    expect($result['cohort'])->toBe(3)
        ->and($result['peer_median'])->toBe(20000.0)
        ->and($result['your_value'])->toBe(20000.0);
});
