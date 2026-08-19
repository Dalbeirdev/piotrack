<?php

declare(strict_types=1);

/**
 * QA §27 / §32 / Reputation.
 *
 * §27's article is written and moved through the editorial workflow for real:
 * "CMMC Compliance Checklist for Philadelphia Manufacturers".
 *
 * The reputation half carries this run's other honesty finding. A reply to a
 * review is stored and the review marked responded, but ReviewProvider exposes
 * only fetch(): no driver can post a reply to Google, Clutch or anywhere else.
 * A user answering a one-star review sees it marked responded and reasonably
 * concludes the public has seen the answer. They have not.
 */

use App\Content\Contracts\ReviewProvider;
use App\Models\ContentPiece;
use App\Models\Review;
use App\Models\SocialPost;
use App\Services\Content\ContentService;
use App\Services\Content\ReputationService;
use App\Services\Content\SocialService;
use App\Support\CurrentOrganization;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('Acme Managed IT Services');
    subscribeOrganization($this->org, 'enterprise');
    app(CurrentOrganization::class)->set($this->org);
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

/*
|--------------------------------------------------------------------------
| §27 - content lifecycle
|--------------------------------------------------------------------------
*/

it('takes the CMMC checklist article from draft to published', function () {
    $content = app(ContentService::class);

    $piece = $content->create([
        'title' => 'CMMC Compliance Checklist for Philadelphia Manufacturers',
        'type' => 'blog',
        'target_keyword' => 'CMMC compliance checklist',
        'meta_title' => 'CMMC Compliance Checklist | Acme Managed IT',
        'meta_description' => 'A practical CMMC Level 2 checklist for Philadelphia manufacturers, from scoping to evidence.',
        'body' => str_repeat('CMMC Level 2 requires documented policies, evidence collection and continuous monitoring. ', 30),
        'status' => 'draft',
    ]);

    expect($piece->organization_id)->toBe($this->org->id)
        ->and($piece->status)->toBe('draft')
        // §27 asks for a content score; it must reflect the piece, not a constant.
        ->and($piece->optimization_score)->toBeGreaterThan(0);

    $bare = $content->create(['title' => 'Untitled', 'type' => 'blog', 'status' => 'draft']);

    expect($piece->optimization_score)->toBeGreaterThan($bare->optimization_score);

    // The editorial workflow runs in order.
    foreach (['in_review', 'approved', 'published'] as $stage) {
        $piece = $content->transition($piece->fresh(), $stage);
    }

    expect($piece->fresh()->status)->toBe('published')
        ->and($piece->fresh()->published_at)->not->toBeNull();
});

it('refuses to skip the editorial workflow', function () {
    $content = app(ContentService::class);

    $piece = $content->create([
        'title' => 'CMMC Compliance Checklist for Philadelphia Manufacturers',
        'type' => 'blog', 'body' => 'Body copy.', 'status' => 'draft',
    ]);

    // Draft straight to published must be refused.
    expect(fn () => $content->transition($piece, 'published'))
        ->toThrow(ValidationException::class);

    expect($piece->fresh()->status)->toBe('draft');
});

it('refuses to publish an empty article', function () {
    $content = app(ContentService::class);

    $piece = $content->create(['title' => 'Placeholder', 'type' => 'blog', 'status' => 'draft']);
    $piece = $content->transition($piece, 'in_review');
    $piece = $content->transition($piece->fresh(), 'approved');

    expect(fn () => $content->transition($piece->fresh(), 'published'))
        ->toThrow(ValidationException::class);

    expect(ContentPiece::find($piece->id)->status)->not->toBe('published');
});

/*
|--------------------------------------------------------------------------
| Reputation - what "responded" actually means
|--------------------------------------------------------------------------
*/

it('marks a review response as written but never published to the platform', function () {
    app(ReputationService::class)->import('google', 'acme-philadelphia');

    $review = Review::firstOrFail();

    app(ReputationService::class)->respond($review, 'Thank you - we have raised this with the service desk lead.');

    $review->refresh();

    expect($review->responded)->toBeTrue()
        ->and($review->response)->toContain('service desk lead')
        // The part that matters: nothing reached Google.
        ->and($review->response_published_at)->toBeNull();

    // And the audit says so rather than implying a public reply.
    $entry = DB::table('audit_logs')->where('action', 'content.review.responded')->first();

    expect(json_decode((string) $entry?->context, true))
        ->toBe(['published_externally' => false]);
});

it('confirms no driver can publish a review response', function () {
    // The limitation is structural: the contract has no method for it. If one
    // is ever added this fails, and respond() must then set
    // response_published_at rather than leaving it null.
    $methods = get_class_methods(ReviewProvider::class);

    expect($methods)->toBe(['fetch']);
});

it('tells the reputation screen that replies stay internal', function () {
    $this->actingAs($this->owner)->get(route('content.reputation.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('reviewSource')
            ->where('reviewSource.name', 'fixture')
            ->where('reviewSource.live', false)
            ->where('reviewSource.canPublishResponses', false));
});

/*
|--------------------------------------------------------------------------
| §32 - social
|--------------------------------------------------------------------------
*/

it('does not double-publish a post that is already published', function () {
    $post = SocialPost::create([
        'channel' => 'linkedin', 'type' => 'text',
        'body' => 'CMMC compliance checklist for Philadelphia manufacturers.',
        'status' => 'draft',
    ]);

    app(SocialService::class)->publish($post);
    $firstPublishedAt = $post->fresh()->published_at;
    $firstExternalId = $post->fresh()->external_id;

    app(SocialService::class)->publish($post->fresh());

    expect($post->fresh()->published_at?->toIso8601String())->toBe($firstPublishedAt?->toIso8601String())
        ->and($post->fresh()->external_id)->toBe($firstExternalId);
});

it('isolates reviews and social posts across tenants', function () {
    app(ReputationService::class)->import('google', 'acme-philadelphia');
    SocialPost::create(['channel' => 'linkedin', 'type' => 'text', 'body' => 'Acme post', 'status' => 'draft']);

    app(CurrentOrganization::class)->forget();
    [$rival] = makeOrganization('Northstar Cybersecurity');
    subscribeOrganization($rival, 'enterprise');
    app(CurrentOrganization::class)->set($rival);

    expect(Review::count())->toBe(0)
        ->and(SocialPost::count())->toBe(0);
});
