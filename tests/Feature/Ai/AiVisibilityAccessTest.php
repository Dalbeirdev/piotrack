<?php

use App\Authorization\Role;
use App\Models\AiPrompt;
use App\Models\AiVisibilityCheck;
use App\Services\Ai\AiVisibilityDashboard;
use App\Support\CurrentOrganization;

it('runs the prompt library across engines and is idempotent per day', function () {
    [$org] = aiOrganization();
    app(CurrentOrganization::class)->set($org);

    AiPrompt::create(['text' => 'best msp in toronto', 'service' => 'managed it', 'city' => 'Toronto', 'is_active' => true]);
    AiPrompt::create(['text' => 'inactive prompt', 'is_active' => false]);

    $dashboard = app(AiVisibilityDashboard::class);
    $first = $dashboard->runLibrary('Piotrack MSP', ['chatgpt', 'gemini']);
    $second = $dashboard->runLibrary('Piotrack MSP', ['chatgpt', 'gemini']); // same day
    app(CurrentOrganization::class)->forget();

    expect($first)->toBe(2)      // 1 active prompt x 2 engines
        ->and($second)->toBe(0)  // idempotent
        ->and(AiVisibilityCheck::withoutGlobalScope('tenant')->count())->toBe(2);
});

it('computes mention, citation and recommendation frequencies', function () {
    [$org] = aiOrganization();
    app(CurrentOrganization::class)->set($org);

    AiVisibilityCheck::create(['prompt' => 'a', 'engine' => 'chatgpt', 'brand' => 'B', 'mentioned' => true, 'recommended' => true, 'position' => 1, 'cited_sources' => ['b.com'], 'competitors' => ['rival.com'], 'share_of_answer' => 60, 'checked_at' => now()]);
    AiVisibilityCheck::create(['prompt' => 'b', 'engine' => 'chatgpt', 'brand' => 'B', 'mentioned' => true, 'recommended' => false, 'position' => 7, 'cited_sources' => [], 'competitors' => ['rival.com'], 'share_of_answer' => 20, 'checked_at' => now()]);
    AiVisibilityCheck::create(['prompt' => 'c', 'engine' => 'gemini', 'brand' => 'B', 'mentioned' => false, 'recommended' => false, 'position' => null, 'cited_sources' => [], 'competitors' => [], 'share_of_answer' => 0, 'checked_at' => now()]);

    $dashboard = app(AiVisibilityDashboard::class);
    $freq = $dashboard->frequencies();
    $sov = $dashboard->shareOfVoice();
    $competitors = $dashboard->competitorComparison();
    app(CurrentOrganization::class)->forget();

    expect($freq['checks'])->toBe(3)
        ->and($freq['mention_rate'])->toBe(66.67)
        ->and($freq['citation_rate'])->toBe(33.33)
        ->and($freq['recommendation_rate'])->toBe(33.33)
        ->and($sov)->toBe(26.67)
        ->and($competitors[0]['domain'])->toBe('rival.com')
        ->and($competitors[0]['appearances'])->toBe(2);
});

it('reports zeroes with no checks rather than inventing numbers', function () {
    [$org] = aiOrganization();
    app(CurrentOrganization::class)->set($org);
    $dashboard = app(AiVisibilityDashboard::class);

    expect($dashboard->frequencies()['mention_rate'])->toBe(0.0)
        ->and($dashboard->shareOfVoice())->toBe(0.0)
        ->and($dashboard->byEngine())->toBe([])
        ->and($dashboard->competitorComparison())->toBe([]);
    app(CurrentOrganization::class)->forget();
});

it('breaks visibility down per engine and per dimension', function () {
    [$org] = aiOrganization();
    app(CurrentOrganization::class)->set($org);

    $prompt = AiPrompt::create(['text' => 'msp toronto', 'service' => 'managed it', 'city' => 'Toronto', 'is_active' => true]);
    AiVisibilityCheck::create(['ai_prompt_id' => $prompt->id, 'prompt' => 'msp toronto', 'engine' => 'chatgpt', 'brand' => 'B', 'mentioned' => true, 'recommended' => true, 'position' => 2, 'cited_sources' => [], 'competitors' => [], 'share_of_answer' => 50, 'checked_at' => now()]);
    AiVisibilityCheck::create(['ai_prompt_id' => $prompt->id, 'prompt' => 'msp toronto', 'engine' => 'gemini', 'brand' => 'B', 'mentioned' => false, 'recommended' => false, 'position' => null, 'cited_sources' => [], 'competitors' => [], 'share_of_answer' => 0, 'checked_at' => now()]);

    $dashboard = app(AiVisibilityDashboard::class);
    $byEngine = collect($dashboard->byEngine());
    $byCity = $dashboard->byDimension('city');
    app(CurrentOrganization::class)->forget();

    expect($byEngine->firstWhere('engine', 'chatgpt')['mention_rate'])->toBe(100.0)
        ->and($byEngine->firstWhere('engine', 'gemini')['mention_rate'])->toBe(0.0)
        ->and($byCity[0]['value'])->toBe('Toronto')
        ->and($byCity[0]['checks'])->toBe(2)
        ->and($byCity[0]['mention_rate'])->toBe(50.0)
        ->and($dashboard->byDimension('nonsense'))->toBe([]);
});

it('alerts when the mention rate shifts between windows', function () {
    [$org] = aiOrganization();
    app(CurrentOrganization::class)->set($org);

    // Previous window: not mentioned. Current window: mentioned.
    AiVisibilityCheck::create(['prompt' => 'a', 'engine' => 'chatgpt', 'brand' => 'B', 'mentioned' => false, 'recommended' => false, 'cited_sources' => [], 'competitors' => [], 'share_of_answer' => 0, 'checked_at' => now()->subDays(10)]);
    AiVisibilityCheck::create(['prompt' => 'a', 'engine' => 'chatgpt', 'brand' => 'B', 'mentioned' => true, 'recommended' => true, 'position' => 1, 'cited_sources' => [], 'competitors' => [], 'share_of_answer' => 70, 'checked_at' => now()->subDay()]);

    $alert = app(AiVisibilityDashboard::class)->alert(7, 10.0);
    $trend = app(AiVisibilityDashboard::class)->trend();
    app(CurrentOrganization::class)->forget();

    expect($alert['changed'])->toBeTrue()
        ->and($alert['direction'])->toBe('up')
        ->and($alert['current'])->toBe(100.0)
        ->and($alert['previous'])->toBe(0.0)
        ->and($trend)->not->toBeEmpty();
});

it('records visibility checks per tenant from the scheduled command', function () {
    [$orgA] = aiOrganization('A');
    [$orgB] = aiOrganization('B');

    foreach ([$orgA, $orgB] as $org) {
        app(CurrentOrganization::class)->set($org);
        AiPrompt::create(['text' => 'best msp', 'is_active' => true]);
        app(CurrentOrganization::class)->forget();
    }

    $this->artisan('ai:run-visibility-checks')->assertSuccessful();

    foreach ([$orgA, $orgB] as $org) {
        expect(AiVisibilityCheck::withoutGlobalScope('tenant')->where('organization_id', $org->id)->count())
            ->toBe(count(AiVisibilityDashboard::ENGINES));
    }
});

it('lets a viewer read AI surfaces but not use the agent', function () {
    [$org] = aiOrganization();
    $viewer = addMember($org, Role::Viewer);

    $this->actingAs($viewer)->get(route('ai.dashboard'))->assertOk();
    $this->actingAs($viewer)->post(route('ai.agent.objection'), ['objection' => 'too costly'])->assertForbidden();
});

it('stops a sales rep from managing prompt templates', function () {
    [$org] = aiOrganization();
    $rep = addMember($org, Role::SalesRepresentative);

    $this->actingAs($rep)->get(route('ai.prompts.index'))->assertOk();
    $this->actingAs($rep)->post(route('ai.prompts.publish'), ['key' => 'sales.qualify', 'template' => 'hacked'])->assertForbidden();
});

it('blocks the AI module without the plan feature', function () {
    [, $owner] = makeOrganization(); // Growth trial: no `ai`

    $this->actingAs($owner)->get(route('ai.dashboard'))->assertForbidden();
});

it('isolates AI data across tenants', function () {
    [, $ownerA] = aiOrganization('Tenant A');
    [$orgB] = aiOrganization('Tenant B');

    app(CurrentOrganization::class)->set($orgB);
    $promptB = AiPrompt::create(['text' => 'B prompt', 'is_active' => true]);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($ownerA)->delete(route('ai.visibility.prompts.destroy', $promptB->id))->assertNotFound();
    expect(AiPrompt::withoutGlobalScope('tenant')->whereKey($promptB->id)->exists())->toBeTrue();
});
