<?php

declare(strict_types=1);

/**
 * QA §28/§29/§30 - AI features around the provider boundary.
 *
 * §28 asks for real provider calls and says not to test only mocked responses.
 * With AI_DRIVER=fixture and no keys that is not possible here, and the module
 * report says so rather than claiming otherwise. What IS verifiable is
 * everything on this side of the boundary: that a result is saved against the
 * right tenant, that usage and cost are recorded, that failures and exhausted
 * credits are handled, and - the part §30 cares most about - that fabricated
 * output is never presented as a finding.
 *
 * §30's "never fabricate AI visibility data" was the live risk.
 * FixtureAiSearchProvider derives mention, position and share-of-answer from a
 * hash, and returns invented competitor domains and an invented citation. Those
 * were stored with no record of where they came from.
 */

use App\Models\AiVisibilityCheck;
use App\Seo\SeoProviderManager;
use App\Services\Seo\AiVisibilityService;
use App\Support\CurrentOrganization;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('Acme Managed IT Services');
    subscribeOrganization($this->org, 'enterprise');
    app(CurrentOrganization::class)->set($this->org);
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

it('stamps every AI visibility check with the driver that produced it', function () {
    $check = app(AiVisibilityService::class)
        ->check('Best MSP in Philadelphia', 'Acme Managed IT Services', 'chatgpt');

    expect($check->provider)->toBe('fixture')
        ->and($check->organization_id)->toBe($this->org->id);

    // The invented material must never sit in the record unattributed.
    expect($check->competitors)->not->toBeEmpty()
        ->and($check->provider)->not->toBeNull('invented competitors stored without provenance');

    expect(AiVisibilityCheck::whereNull('provider')->count())->toBe(0);
});

it('records the driver in the audit trail for an AI check', function () {
    app(AiVisibilityService::class)->check('CMMC consultant Philadelphia', 'Acme Managed IT Services');

    $entry = DB::table('audit_logs')->where('action', 'seo.ai.checked')->first();
    $context = json_decode((string) $entry?->context, true);

    expect($context)->toHaveKey('provider')
        ->and($context['provider'])->toBe('fixture');
});

it('reports the AI search driver as not live', function () {
    $providers = app(SeoProviderManager::class);

    expect($providers->aiProviderName())->toBe('fixture')
        ->and($providers->isAiLive())->toBeFalse();

    config()->set('seo.ai_provider', 'openai');

    expect($providers->aiProviderName())->toBe('openai')
        ->and($providers->isAiLive())->toBeTrue();
});

it('tells both AI visibility screens where their findings came from', function () {
    // Two surfaces render the same invented competitors and citations:
    // /seo/ai-visibility and /ai/visibility. Both must disclose.
    foreach ([route('seo.ai.index'), route('ai.visibility.index')] as $url) {
        $this->actingAs($this->owner)->get($url)
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->has('aiSource')
                ->where('aiSource.name', 'fixture')
                ->where('aiSource.live', false));
    }
});

it('keeps fixture visibility deterministic across repeated checks', function () {
    $service = app(AiVisibilityService::class);

    $first = $service->check('Best MSP in Philadelphia', 'Acme Managed IT Services');
    $second = $service->check('Best MSP in Philadelphia', 'Acme Managed IT Services');

    // Same inputs, same answer - otherwise trend reporting would show movement
    // that never happened.
    expect($second->mentioned)->toBe($first->mentioned)
        ->and($second->position)->toBe($first->position)
        ->and($second->share_of_answer)->toBe($first->share_of_answer);

    // History accumulates rather than overwriting.
    expect(AiVisibilityCheck::where('prompt', 'Best MSP in Philadelphia')->count())->toBe(2);
});

it('isolates AI visibility history between tenants', function () {
    app(AiVisibilityService::class)->check('Best MSP in Philadelphia', 'Acme Managed IT Services');

    app(CurrentOrganization::class)->forget();
    [$rival] = makeOrganization('Northstar Cybersecurity');
    subscribeOrganization($rival, 'enterprise');
    app(CurrentOrganization::class)->set($rival);

    expect(AiVisibilityCheck::count())->toBe(0);
});
