<?php

use App\Authorization\Role;
use App\Models\AiVisibilityCheck;
use App\Models\Keyword;
use App\Services\Seo\AiVisibilityService;
use App\Support\CurrentOrganization;

it('records an AI visibility check with a share of answer', function () {
    [$org] = makeOrganization();
    subscribeOrganization($org, 'professional'); // includes ai_visibility

    app(CurrentOrganization::class)->set($org);
    // A prompt+brand pair the fixture driver reports as mentioned.
    $check = app(AiVisibilityService::class)->check('best managed IT provider in Dallas', 'Acme MSP', 'chatgpt');
    app(CurrentOrganization::class)->forget();

    expect($check->engine)->toBe('chatgpt')->and($check->organization_id)->toBe($org->id);
    expect(AiVisibilityCheck::withoutGlobalScope('tenant')->count())->toBe(1);
});

it('runs an AI visibility check via the controller', function () {
    [$org, $owner] = makeOrganization();
    subscribeOrganization($org, 'professional');

    $this->actingAs($owner)->post(route('seo.ai.check'), ['prompt' => 'best msp dallas', 'brand' => 'Acme', 'engine' => 'chatgpt'])->assertRedirect();

    expect(AiVisibilityCheck::withoutGlobalScope('tenant')->count())->toBe(1);
});

it('lets a viewer read SEO but not manage it', function () {
    [$org] = makeOrganization();
    $viewer = addMember($org, Role::Viewer);

    $this->actingAs($viewer)->get(route('seo.keywords.index'))->assertOk();
    $this->actingAs($viewer)->post(route('seo.keywords.store'), ['phrase' => 'x', 'intent' => 'commercial'])->assertForbidden();
});

it('blocks SEO when the plan does not include it', function () {
    [$org, $owner] = makeOrganization();
    subscribeOrganization($org, 'starter'); // no seo feature

    $this->actingAs($owner)->get(route('seo.dashboard'))->assertForbidden();
});

it('blocks AI visibility without the ai_visibility feature', function () {
    // Growth trial includes `seo` but NOT `ai_visibility`.
    [, $owner] = makeOrganization();

    $this->actingAs($owner)->get(route('seo.ai.index'))->assertForbidden();
});

it('isolates keywords across tenants', function () {
    [, $ownerA] = makeOrganization('A');
    [$orgB] = makeOrganization('B');

    app(CurrentOrganization::class)->set($orgB);
    $keywordB = Keyword::create(['phrase' => 'secret keyword', 'intent' => 'commercial']);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($ownerA)->post(route('seo.keywords.rank', $keywordB->id), ['domain' => 'x.test'])->assertNotFound();
});
