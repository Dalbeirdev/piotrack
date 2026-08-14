<?php

use App\Models\Review;
use App\Models\ReviewRequest;
use App\Services\Content\ReputationService;
use App\Support\CurrentOrganization;

it('derives sentiment from rating', function () {
    $service = app(ReputationService::class);

    expect($service->sentiment(5))->toBe('positive')
        ->and($service->sentiment(4))->toBe('positive')
        ->and($service->sentiment(3))->toBe('neutral')
        ->and($service->sentiment(1))->toBe('negative');
});

it('records a review with derived sentiment via the controller', function () {
    [$org, $owner] = makeOrganization();

    $this->actingAs($owner)->post(route('content.reputation.reviews.store'), [
        'source' => 'google', 'rating' => 5, 'body' => 'Great service',
    ])->assertRedirect();

    $review = Review::withoutGlobalScope('tenant')->firstWhere('source', 'google');
    expect($review->sentiment)->toBe('positive');
});

it('aggregates rating, count and sentiment', function () {
    [$org] = makeOrganization();
    app(CurrentOrganization::class)->set($org);
    $service = app(ReputationService::class);
    $service->recordReview(['source' => 'google', 'rating' => 5, 'body' => 'a']);
    $service->recordReview(['source' => 'google', 'rating' => 4, 'body' => 'b']);
    $service->recordReview(['source' => 'clutch', 'rating' => 2, 'body' => 'c']);
    $aggregate = $service->aggregate();
    app(CurrentOrganization::class)->forget();

    expect($aggregate['count'])->toBe(3)
        ->and($aggregate['average'])->toBe(3.67)
        ->and($aggregate['by_sentiment']['positive'])->toBe(2)
        ->and($aggregate['by_sentiment']['negative'])->toBe(1);
});

it('imports reviews via the fixture provider', function () {
    [$org] = makeOrganization();
    app(CurrentOrganization::class)->set($org);
    $count = app(ReputationService::class)->import('google', 'place-abc');
    app(CurrentOrganization::class)->forget();

    expect($count)->toBe(3);
    expect(Review::withoutGlobalScope('tenant')->count())->toBe(3);
});

it('sends a review request', function () {
    [$org, $owner] = makeOrganization();
    app(CurrentOrganization::class)->set($org);
    $request = ReviewRequest::create(['name' => 'Client', 'channel' => 'email', 'status' => 'pending']);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($owner)->post(route('content.reputation.requests.send', $request->id))->assertRedirect();
    expect($request->refresh()->status)->toBe('sent')->and($request->sent_at)->not->toBeNull();
});

it('responds to a review', function () {
    [$org, $owner] = makeOrganization();
    app(CurrentOrganization::class)->set($org);
    $review = Review::create(['source' => 'google', 'rating' => 2, 'sentiment' => 'negative', 'body' => 'bad']);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($owner)->post(route('content.reputation.reviews.respond', $review->id), ['response' => 'We will fix this.'])->assertRedirect();
    expect($review->refresh()->responded)->toBeTrue();
});
