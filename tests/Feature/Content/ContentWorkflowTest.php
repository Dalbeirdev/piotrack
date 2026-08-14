<?php

use App\Models\AuditLog;
use App\Models\ContentPiece;
use App\Services\Content\ContentService;
use App\Support\CurrentOrganization;
use Illuminate\Validation\ValidationException;

it('creates a content piece and computes an optimization score', function () {
    [$org, $owner] = makeOrganization();

    $this->actingAs($owner)->post(route('content.pieces.store'), [
        'title' => 'The Complete Guide to Managed IT Services for SMBs',
        'content_type' => 'guide',
        'target_keyword' => 'managed it services',
        'cta' => 'Book a call',
        'is_lead_magnet' => true,
    ])->assertRedirect();

    $piece = ContentPiece::withoutGlobalScope('tenant')->firstWhere('content_type', 'guide');
    expect($piece)->not->toBeNull()
        ->and($piece->organization_id)->toBe($org->id)
        ->and($piece->optimization_score)->toBeGreaterThan(0);
    expect(AuditLog::withoutGlobalScope('tenant')->where('action', 'content.piece.created')->exists())->toBeTrue();
});

it('scores an optimized piece higher than a bare one', function () {
    [$org] = makeOrganization();
    app(CurrentOrganization::class)->set($org);
    $service = app(ContentService::class);

    $good = $service->create([
        'title' => 'The Complete Guide to Managed IT Services for SMBs Today',
        'content_type' => 'guide',
        'target_keyword' => 'managed it',
        'cta' => 'Book a call',
        'excerpt' => 'Everything an SMB needs to know.',
        'body' => '<a href="/a">one</a> <a href="/b">two</a> '.str_repeat('word ', 650),
    ]);
    $bare = $service->create(['title' => 'X', 'content_type' => 'article']);
    app(CurrentOrganization::class)->forget();

    expect($good->optimization_score)->toBeGreaterThan($bare->optimization_score)
        ->and($good->optimization_score)->toBeGreaterThan(80);
});

it('enforces the editorial workflow order', function () {
    [$org] = makeOrganization();
    app(CurrentOrganization::class)->set($org);
    $piece = ContentPiece::create(['title' => 'T', 'slug' => 't-order', 'content_type' => 'article', 'status' => 'idea', 'body' => 'body']);
    $service = app(ContentService::class);

    // idea → published is not allowed.
    expect(fn () => $service->transition($piece, 'published'))->toThrow(ValidationException::class);

    // idea → draft is allowed.
    $service->transition($piece, 'draft');
    expect($piece->refresh()->status)->toBe('draft');
    app(CurrentOrganization::class)->forget();
});

it('requires a body before publishing', function () {
    [$org] = makeOrganization();
    app(CurrentOrganization::class)->set($org);
    $piece = ContentPiece::create(['title' => 'T', 'slug' => 't-nobody', 'content_type' => 'article', 'status' => 'approved', 'body' => null]);

    expect(fn () => app(ContentService::class)->transition($piece, 'published'))->toThrow(ValidationException::class);
    app(CurrentOrganization::class)->forget();
});

it('publishes via the status route and stamps published_at', function () {
    [$org, $owner] = makeOrganization();
    app(CurrentOrganization::class)->set($org);
    $piece = ContentPiece::create(['title' => 'T', 'slug' => 't-pub', 'content_type' => 'article', 'status' => 'approved', 'body' => 'Real body here']);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($owner)->post(route('content.pieces.status', $piece->id), ['status' => 'published'])->assertRedirect();

    $piece->refresh();
    expect($piece->status)->toBe('published')->and($piece->published_at)->not->toBeNull();
});
