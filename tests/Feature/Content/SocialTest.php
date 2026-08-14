<?php

use App\Jobs\PublishSocialPost;
use App\Models\SocialPost;
use App\Services\Content\SocialService;
use App\Support\CurrentOrganization;
use Illuminate\Support\Facades\Queue;

it('publishes a post and captures engagement metrics', function () {
    [$org] = makeOrganization();
    app(CurrentOrganization::class)->set($org);
    $post = SocialPost::create(['channel' => 'linkedin', 'body' => 'Hello world', 'status' => 'draft']);
    app(SocialService::class)->publish($post);
    app(CurrentOrganization::class)->forget();

    $post->refresh();
    expect($post->status)->toBe('published')
        ->and($post->external_id)->not->toBeNull()
        ->and($post->published_at)->not->toBeNull()
        ->and($post->impressions)->toBeGreaterThan(0);
});

it('is a no-op when publishing an already-published post', function () {
    [$org] = makeOrganization();
    app(CurrentOrganization::class)->set($org);
    $post = SocialPost::create(['channel' => 'x', 'body' => 'Hi', 'status' => 'published', 'published_at' => now(), 'impressions' => 100]);
    app(SocialService::class)->publish($post);
    app(CurrentOrganization::class)->forget();

    expect($post->refresh()->impressions)->toBe(100);
});

it('dispatches only due scheduled posts', function () {
    Queue::fake();
    [$org] = makeOrganization();
    app(CurrentOrganization::class)->set($org);
    SocialPost::create(['channel' => 'linkedin', 'body' => 'Due', 'status' => 'scheduled', 'scheduled_at' => now()->subMinute()]);
    SocialPost::create(['channel' => 'linkedin', 'body' => 'Future', 'status' => 'scheduled', 'scheduled_at' => now()->addDay()]);
    $count = app(SocialService::class)->dispatchDue();
    app(CurrentOrganization::class)->forget();

    expect($count)->toBe(1);
    Queue::assertPushed(PublishSocialPost::class, 1);
});

it('publishes a post via the controller', function () {
    [$org, $owner] = makeOrganization();
    app(CurrentOrganization::class)->set($org);
    $post = SocialPost::create(['channel' => 'linkedin', 'body' => 'X', 'status' => 'draft']);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($owner)->post(route('content.social.publish', $post->id))->assertRedirect();
    expect($post->refresh()->status)->toBe('published');
});
