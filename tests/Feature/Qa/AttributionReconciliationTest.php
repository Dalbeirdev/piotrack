<?php

declare(strict_types=1);

/**
 * QA §33/§34 - the attribution chain, and the dashboard reconciled against the
 * raw records behind it.
 *
 * §34 is explicit: "Compare dashboard values against raw database records. No
 * unexplained discrepancy is acceptable." So this builds a dataset of known
 * shape, then asserts each reported metric equals what an independent count of
 * the tables gives - not that the numbers merely look plausible.
 *
 * §33's chain is Google Ads -> campaign -> keyword -> landing page -> form ->
 * lead -> SQL -> meeting -> opportunity -> closed won -> $4,500 MRR ->
 * $54,000 ARR. Ten of the twelve hops are traceable. Keyword and landing page
 * are not: nothing captures utm_term, gclid or the page a submission came from,
 * so those two are asserted as absent rather than faked. The register already
 * says so at ATTR-006 and ATTR-007 ("needs the tracking pixel + GSC/ad-platform
 * click-id join"), and this pins it.
 */

use App\Models\AdCampaign;
use App\Models\AdMetric;
use App\Models\Booking;
use App\Models\BookingPage;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Services\Analytics\AnalyticsService;
use App\Services\Analytics\AttributionService;
use App\Support\CurrentOrganization;

const CAMPAIGN = 'Philadelphia CMMC Lead Generation';

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('Acme Managed IT Services');
    subscribeOrganization($this->org, 'enterprise');
    app(CurrentOrganization::class)->set($this->org);

    $this->pipeline = Pipeline::where('is_default', true)->firstOrFail();
    $this->stages = $this->pipeline->stages()->orderBy('sort_order')->get();
    $this->won = $this->stages->firstWhere('is_won', true);
    $this->open = $this->stages->first(fn ($s) => ! $s->is_won && ! $s->is_lost);
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

/** Ten contacts across lifecycle stages, three meetings, two open deals, one won. */
function buildKnownFunnel(object $ctx): void
{
    // 10 leads total: 7 mql, 2 sql, 1 customer.
    $stages = array_merge(array_fill(0, 7, 'mql'), array_fill(0, 2, 'sql'), ['customer']);

    foreach ($stages as $i => $stage) {
        Contact::create([
            'first_name' => 'Prospect', 'last_name' => (string) $i,
            'email' => "prospect{$i}@precisionmfg-test.com",
            'lifecycle_stage' => $stage,
            'lead_source' => 'paid',
            'campaign' => CAMPAIGN,
        ]);
    }

    $page = BookingPage::create([
        'name' => 'CMMC assessment', 'slug' => 'cmmc-assessment',
        'duration_minutes' => 30, 'is_active' => true,
    ]);

    foreach (range(1, 3) as $i) {
        Booking::create([
            'booking_page_id' => $page->id,
            'name' => "Attendee {$i}",
            'email' => "attendee{$i}@precisionmfg-test.com",
            'scheduled_at' => now()->addDays($i),
            'status' => 'scheduled',
        ]);
    }

    foreach (range(1, 2) as $i) {
        Deal::create([
            'pipeline_id' => $ctx->pipeline->id, 'stage_id' => $ctx->open->id,
            'name' => "Open opportunity {$i}", 'value' => 1_200_000,
            'lead_source' => 'paid', 'campaign' => CAMPAIGN,
        ]);
    }

    Deal::create([
        'pipeline_id' => $ctx->pipeline->id, 'stage_id' => $ctx->won->id,
        'name' => 'Precision Manufacturing - Cybersecurity Managed Services',
        'value' => 5_400_000, 'mrr' => 450_000, 'contract_term_months' => 12,
        'status' => 'won', 'lead_source' => 'paid', 'campaign' => CAMPAIGN,
        'closed_at' => now(),
    ]);
}

it('reports a funnel that matches an independent count of the tables', function () {
    buildKnownFunnel($this);

    $funnel = app(AnalyticsService::class)->funnel();

    // Each expectation is recomputed from the raw tables, so a change to either
    // the dashboard query or the data shows up as a discrepancy.
    expect($funnel['leads'])->toBe(Contact::count())
        ->and($funnel['leads'])->toBe(10)
        ->and($funnel['mqls'])->toBe(Contact::where('lifecycle_stage', 'mql')->count())
        ->and($funnel['mqls'])->toBe(7)
        ->and($funnel['sqls'])->toBe(Contact::where('lifecycle_stage', 'sql')->count())
        ->and($funnel['sqls'])->toBe(2)
        ->and($funnel['meetings'])->toBe(Booking::count())
        ->and($funnel['meetings'])->toBe(3)
        ->and($funnel['opportunities'])->toBe(2)
        ->and($funnel['closed_won'])->toBe(1)
        ->and($funnel['qualified_pipeline'])->toBe(2_400_000);
});

it('reports revenue that matches the won deals exactly', function () {
    buildKnownFunnel($this);

    $revenue = app(AnalyticsService::class)->revenue();
    $wonDeals = Deal::whereHas('stage', fn ($q) => $q->where('is_won', true));

    expect($revenue['mrr'])->toBe((int) (clone $wonDeals)->sum('mrr'))
        ->and($revenue['mrr'])->toBe(450_000)          // $4,500
        ->and($revenue['arr'])->toBe((int) (clone $wonDeals)->sum('arr'))
        ->and($revenue['arr'])->toBe(5_400_000)        // $54,000
        ->and($revenue['contract_value'])->toBe((int) (clone $wonDeals)->sum('value'));

    // Open deals must never leak into recognised revenue.
    expect($revenue['contract_value'])->toBe(5_400_000);
});

it('rolls advertising KPIs up to the sum of the metric rows', function () {
    $campaign = AdCampaign::create([
        'name' => 'Cybersecurity - Philadelphia', 'platform' => 'google_ads',
        'status' => 'active', 'objective' => 'leads',
    ]);

    $days = [
        ['impressions' => 12_000, 'clicks' => 480, 'spend' => 120_000, 'conversions' => 12, 'revenue' => 900_000],
        ['impressions' => 9_500, 'clicks' => 310, 'spend' => 88_000, 'conversions' => 7, 'revenue' => 450_000],
        ['impressions' => 15_250, 'clicks' => 602, 'spend' => 151_000, 'conversions' => 15, 'revenue' => 1_200_000],
    ];

    foreach ($days as $i => $row) {
        AdMetric::create(array_merge($row, [
            'ad_campaign_id' => $campaign->id,
            'date' => now()->subDays($i)->startOfDay(),
        ]));
    }

    $kpi = app(AnalyticsService::class)->advertising();

    expect($kpi->impressions)->toBe((int) AdMetric::sum('impressions'))
        ->and($kpi->impressions)->toBe(36_750)
        ->and($kpi->clicks)->toBe((int) AdMetric::sum('clicks'))
        ->and($kpi->spend)->toBe((int) AdMetric::sum('spend'))
        ->and($kpi->spend)->toBe(359_000)
        ->and($kpi->conversions)->toBe((int) AdMetric::sum('conversions'))
        ->and($kpi->revenue)->toBe((int) AdMetric::sum('revenue'));
});

it('traces revenue back to its channel and campaign', function () {
    buildKnownFunnel($this);

    $attribution = app(AttributionService::class);

    expect($attribution->campaignRevenue())->toHaveKey(CAMPAIGN)
        ->and($attribution->campaignRevenue()[CAMPAIGN])->toBe(5_400_000)
        ->and($attribution->channelRevenue())->toHaveKey('paid')
        ->and($attribution->channelRevenue()['paid'])->toBe(5_400_000);

    // Attributed revenue must equal recognised revenue, or money is being
    // counted in one view and not the other.
    expect(array_sum($attribution->channelRevenue()))
        ->toBe(app(AnalyticsService::class)->revenue()['contract_value']);
});

it('confirms keyword and landing page are not captured on the journey', function () {
    // §33 names both as hops. Nothing records utm_term, gclid, or which page a
    // submission came from, so per-keyword and per-landing-page revenue cannot
    // be derived - matching ATTR-006 / ATTR-007 "Partially Implemented".
    // If capture is ever added this fails, and those rows must be revisited.
    foreach (['contacts', 'deals', 'form_submissions'] as $table) {
        $columns = Schema::getColumnListing($table);

        foreach (['keyword', 'utm_term', 'utm_source', 'utm_campaign', 'gclid', 'landing_page_id'] as $field) {
            expect($columns)->not->toContain($field, "{$table}.{$field} now exists");
        }
    }

    // What IS carried: a manually set source and campaign bucket.
    expect(Schema::getColumnListing('contacts'))->toContain('lead_source')
        ->and(Schema::getColumnListing('contacts'))->toContain('campaign')
        ->and(Schema::getColumnListing('deals'))->toContain('campaign');
});
