<?php

use App\Models\Keyword;
use App\Models\KeywordRanking;
use App\Services\Seo\KeywordService;
use App\Services\Seo\RankTracker;
use App\Support\CurrentOrganization;

it('adds a keyword and rejects a duplicate', function () {
    [$org, $owner] = makeOrganization();

    $this->actingAs($owner)->post(route('seo.keywords.store'), ['phrase' => 'managed it services', 'intent' => 'commercial'])->assertRedirect();
    $this->actingAs($owner)->post(route('seo.keywords.store'), ['phrase' => 'managed it services', 'intent' => 'commercial'])->assertSessionHasErrors('phrase');

    expect(Keyword::withoutGlobalScope('tenant')->where('phrase', 'managed it services')->count())->toBe(1);
});

it('records rank history and updates the current position', function () {
    [$org] = makeOrganization();
    app(CurrentOrganization::class)->set($org);
    $keyword = Keyword::create(['phrase' => 'msp dallas', 'intent' => 'commercial']);
    $ranking = app(RankTracker::class)->check($keyword, 'acme.test');
    app(CurrentOrganization::class)->forget();

    expect($ranking->position)->not->toBeNull()
        ->and($keyword->refresh()->current_position)->toBe($ranking->position);
    expect(KeywordRanking::withoutGlobalScope('tenant')->where('keyword_id', $keyword->id)->count())->toBe(1);
});

it('records a separate competitor ranking', function () {
    [$org] = makeOrganization();
    app(CurrentOrganization::class)->set($org);
    $keyword = Keyword::create(['phrase' => 'it support', 'intent' => 'commercial']);
    $comp = app(RankTracker::class)->checkCompetitor($keyword, 'rival.test');
    app(CurrentOrganization::class)->forget();

    expect($comp->is_competitor)->toBeTrue()->and($comp->competitor_domain)->toBe('rival.test');
});

it('flags page-one and top-three positions', function () {
    expect(RankTracker::isPageOne(7))->toBeTrue()
        ->and(RankTracker::isPageOne(11))->toBeFalse()
        ->and(RankTracker::isTopThree(3))->toBeTrue()
        ->and(RankTracker::isTopThree(4))->toBeFalse()
        ->and(RankTracker::isPageOne(null))->toBeFalse();
});

it('clusters keywords and finds the content gap', function () {
    [$org] = makeOrganization();
    app(CurrentOrganization::class)->set($org);
    Keyword::create(['phrase' => 'managed it services', 'intent' => 'commercial', 'mapped_url' => 'https://acme.test/it']);
    Keyword::create(['phrase' => 'cloud backup pricing', 'intent' => 'transactional']); // unmapped

    $service = app(KeywordService::class);
    $service->recluster();
    $gap = $service->contentGap();
    app(CurrentOrganization::class)->forget();

    expect($gap)->toHaveCount(1)->and($gap->first()->phrase)->toBe('cloud backup pricing');
    expect(Keyword::withoutGlobalScope('tenant')->where('phrase', 'managed it services')->value('cluster'))->toBe('managed');
});

it('checks a keyword rank via the controller', function () {
    [$org, $owner] = makeOrganization();
    app(CurrentOrganization::class)->set($org);
    $keyword = Keyword::create(['phrase' => 'dallas msp', 'intent' => 'commercial']);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($owner)->post(route('seo.keywords.rank', $keyword->id), ['domain' => 'acme.test'])->assertRedirect();

    expect(KeywordRanking::withoutGlobalScope('tenant')->where('keyword_id', $keyword->id)->count())->toBe(1);
});
