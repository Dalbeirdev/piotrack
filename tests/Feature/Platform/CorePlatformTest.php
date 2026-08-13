<?php

use App\Authorization\Role;

it('health endpoint reports queue and storage checks', function () {
    $response = $this->get('/health');

    $response->assertOk()
        ->assertJsonPath('checks.database', true)
        ->assertJsonPath('checks.cache', true)
        ->assertJsonPath('checks.queue', true)
        ->assertJsonPath('checks.storage', true)
        ->assertJsonStructure(['metrics' => ['queue_pending', 'queue_failed']]);
});

it('surfaces an onboarding checklist derived from real state', function () {
    [$org, $owner] = makeOrganization();

    $this->actingAs($owner)->get(route('dashboard'))->assertInertia(fn ($page) => $page
        ->component('dashboard')
        ->where('onboarding.complete', false)
        ->where('onboarding.steps', fn ($steps) => collect($steps)->firstWhere('key', 'create_org')['done'] === true
            && collect($steps)->firstWhere('key', 'invite_team')['done'] === false),
    );
});

it('marks onboarding steps done as state changes', function () {
    [$org, $owner] = makeOrganization();
    subscribeOrganization($org, 'growth'); // choose_plan → done
    addMember($org, Role::Admin); // invite_team → done

    $this->actingAs($owner)->get(route('dashboard'))->assertInertia(fn ($page) => $page
        ->where('onboarding.steps', fn ($steps) => collect($steps)->firstWhere('key', 'choose_plan')['done'] === true
            && collect($steps)->firstWhere('key', 'invite_team')['done'] === true),
    );
});
