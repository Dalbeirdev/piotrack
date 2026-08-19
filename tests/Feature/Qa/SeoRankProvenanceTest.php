<?php

declare(strict_types=1);

/**
 * QA §25 - "Verify ranking data is not fabricated."
 *
 * It is: with the default configuration, a position comes from
 * FixtureRankProvider, which derives a number 1-20 from a CRC32 of the keyword,
 * domain and engine. That is fine for exercising the pipeline and worthless as
 * a ranking.
 *
 * The defect was that nothing said so. keyword_rankings had no provenance
 * column, so a hash was stored identically to a real SERP result, and no SEO
 * controller passed the driver to the UI - while the AI module already did
 * exactly that ("Stated plainly so fixture output is never mistaken for live
 * inference"). A customer on a default install could read a hash as a ranking,
 * and once a real provider was connected the fixture history would stay
 * indistinguishable from real history for ever.
 *
 * Keyword: "CMMC MSP Philadelphia" for Acme Managed IT Services.
 */

use App\Models\Keyword;
use App\Models\KeywordRanking;
use App\Seo\SeoProviderManager;
use App\Services\Seo\RankTracker;
use App\Support\CurrentOrganization;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('Acme Managed IT Services');
    subscribeOrganization($this->org, 'enterprise');
    app(CurrentOrganization::class)->set($this->org);

    $this->keyword = Keyword::create([
        'phrase' => 'CMMC MSP Philadelphia',
        'intent' => 'commercial',
        'type' => 'service',
        'is_tracked' => true,
    ]);
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

it('stamps every recorded position with the driver that produced it', function () {
    $ranking = app(RankTracker::class)->check($this->keyword, 'acme-managed-it-test.com', 'Philadelphia');

    expect($ranking->provider)->toBe('fixture')
        ->and($ranking->position)->toBeGreaterThan(0);

    // Competitor rows carry it too, or half the history is unattributed.
    $competitor = app(RankTracker::class)
        ->checkCompetitor($this->keyword, 'northstar-cyber-test.com', 'Philadelphia');

    expect($competitor->provider)->toBe('fixture')
        ->and($competitor->is_competitor)->toBeTrue();

    // No row may exist without provenance.
    expect(KeywordRanking::whereNull('provider')->count())->toBe(0);
});

it('records the driver in the audit trail alongside the position', function () {
    app(RankTracker::class)->check($this->keyword, 'acme-managed-it-test.com');

    $entry = DB::table('audit_logs')->where('action', 'seo.rank.checked')->first();

    expect($entry)->not->toBeNull();
    expect(json_decode((string) $entry->context, true))->toHaveKey('provider')
        ->and(json_decode((string) $entry->context, true)['provider'])->toBe('fixture');
});

it('reports the fixture driver as not live', function () {
    $providers = app(SeoProviderManager::class);

    expect($providers->rankProviderName())->toBe('fixture')
        ->and($providers->isRankLive())->toBeFalse()
        ->and($providers->isAiLive())->toBeFalse();

    config()->set('seo.rank_provider', 'serpapi');

    expect($providers->rankProviderName())->toBe('serpapi')
        ->and($providers->isRankLive())->toBeTrue();
});

it('tells the SEO screens where positions came from', function () {
    // §55's principle applied to data honesty: the number is useless without
    // its provenance, so the screens that show it must receive that too.
    foreach ([route('seo.dashboard'), route('seo.keywords.index')] as $url) {
        $this->actingAs($this->owner)->get($url)
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->has('rankSource')
                ->where('rankSource.name', 'fixture')
                ->where('rankSource.live', false));
    }
});

it('keeps fixture positions deterministic so the pipeline is testable', function () {
    // The fixture driver's value is reproducibility - the same inputs must give
    // the same position, or rank-change reporting would show phantom movement.
    $first = app(RankTracker::class)->check($this->keyword, 'acme-managed-it-test.com', 'Philadelphia');
    $second = app(RankTracker::class)->check($this->keyword, 'acme-managed-it-test.com', 'Philadelphia');

    expect($second->position)->toBe($first->position);

    // A different domain must give a different lookup, not a constant.
    $other = app(RankTracker::class)->check($this->keyword, 'northstar-cyber-test.com', 'Philadelphia');

    expect($other->position)->toBeGreaterThan(0)
        ->and($other->position)->toBeLessThanOrEqual(20);
});
