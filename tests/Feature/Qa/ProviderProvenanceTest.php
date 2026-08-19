<?php

declare(strict_types=1);

/**
 * QA - provenance across every provider-backed table.
 *
 * Found three times in this QA run (keyword_rankings, ai_visibility_checks,
 * then ad_metrics / reviews / social_posts), so this is the guard rather than
 * another one-off fix: every table that stores what a fixture driver invents
 * must record which driver invented it.
 *
 * The drivers do not produce placeholder-looking output. They produce
 * plausible output: a rank of 4, spend of $3,590, a four-star review reading
 * "Reliable managed IT support with responsive service" filed under Google.
 * That is the point of a fixture and the reason provenance is not optional.
 */

use App\Models\AdCampaign;
use App\Models\Review;
use App\Models\SocialPost;
use App\Services\Advertising\AdMetricsService;
use App\Services\Content\ReputationService;
use App\Services\Content\SocialService;
use App\Support\CurrentOrganization;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('Acme Managed IT Services');
    subscribeOrganization($this->org, 'enterprise');
    app(CurrentOrganization::class)->set($this->org);
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

it('records the driver behind every table a fixture writes to', function () {
    // Every table storing provider-derived values needs somewhere to say so.
    $missing = [];

    foreach ([
        'keyword_rankings', 'ai_visibility_checks',
        'ad_metrics', 'reviews', 'social_posts', 'call_tracking_numbers',
    ] as $table) {
        if (! in_array('provider', Schema::getColumnListing($table), true)) {
            $missing[] = "{$table} stores provider output with no provenance column";
        }
    }

    expect($missing)->toBe([]);
});

it('stamps synthesised advertising metrics with their driver', function () {
    $campaign = AdCampaign::create([
        'name' => 'Cybersecurity - Philadelphia', 'platform' => 'google_ads',
        'status' => 'active', 'objective' => 'leads', 'daily_budget' => 15_000,
    ]);

    app(AdMetricsService::class)->refresh($campaign, 7);

    $metrics = $campaign->metrics()->get();

    expect($metrics)->not->toBeEmpty()
        ->and($metrics->pluck('provider')->unique()->all())->toBe(['fixture']);

    // Spend and revenue are hash-derived; the dashboard computes ROAS from them.
    expect($metrics->sum('spend'))->toBeGreaterThan(0)
        ->and(DB::table('ad_metrics')->whereNull('provider')->count())->toBe(0);
});

it('stamps imported reviews so an invented one is never filed as a real Google review', function () {
    $imported = app(ReputationService::class)->import('google', 'acme-philadelphia');

    expect($imported)->toBeGreaterThan(0);

    $reviews = Review::get();

    // `source` says which platform it is filed under; `provider` says who wrote it.
    expect($reviews->pluck('source')->unique()->all())->toBe(['google'])
        ->and($reviews->pluck('provider')->unique()->all())->toBe(['fixture'])
        ->and($reviews->first()->author_name)->not->toBeEmpty()
        ->and($reviews->first()->rating)->toBeGreaterThan(0);

    expect(Review::whereNull('provider')->count())->toBe(0);
});

it('stamps social engagement that no platform ever reported', function () {
    $post = SocialPost::create([
        'channel' => 'linkedin', 'type' => 'text',
        'body' => 'CMMC compliance checklist for Philadelphia manufacturers.',
        'status' => 'draft',
    ]);

    app(SocialService::class)->publish($post);
    $post->refresh();

    expect($post->provider)->toBe('fixture')
        ->and($post->status)->toBe('published')
        // The external id self-labels, which is good, but the engagement
        // numbers beside it do not.
        ->and($post->external_id)->toStartWith('fixture-')
        ->and($post->impressions)->toBeGreaterThan(0);

    app(SocialService::class)->refreshMetrics($post);

    expect($post->fresh()->provider)->toBe('fixture');
});

it('names the driver in the audit trail when metrics are refreshed', function () {
    $campaign = AdCampaign::create([
        'name' => 'Cybersecurity - Philadelphia', 'platform' => 'google_ads',
        'status' => 'active', 'objective' => 'leads', 'daily_budget' => 15_000,
    ]);

    app(AdMetricsService::class)->refresh($campaign, 3);

    $entry = DB::table('audit_logs')->where('action', 'ads.metrics.refreshed')->first();
    $context = json_decode((string) $entry?->context, true);

    expect($context)->toHaveKey('provider')
        ->and($context['provider'])->toBe('fixture');
});
