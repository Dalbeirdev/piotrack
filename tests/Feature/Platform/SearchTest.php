<?php

use App\Authorization\Role;
use App\Models\Team;
use App\Support\CurrentOrganization;

it('returns grouped tenant-scoped results', function () {
    [$org, $owner] = makeOrganization();
    app(CurrentOrganization::class)->set($org);
    Team::create(['name' => 'Onboarding Squad']);

    $response = $this->actingAs($owner)->getJson(route('search', ['q' => 'Onboarding']));

    $response->assertOk();
    $groups = collect($response->json('groups'));
    expect($groups->contains(fn ($g) => $g['type'] === 'teams'))->toBeTrue();
});

it('never returns another tenant records', function () {
    [$orgA, $ownerA] = makeOrganization('Acme');
    [$orgB] = makeOrganization('Globex');
    app(CurrentOrganization::class)->set($orgB);
    Team::create(['name' => 'Secret Team B']);
    app(CurrentOrganization::class)->forget();

    $response = $this->actingAs($ownerA)->getJson(route('search', ['q' => 'Secret Team B']));

    $items = collect($response->json('groups'))->flatMap(fn ($g) => $g['items']);
    expect($items->contains(fn ($i) => $i['title'] === 'Secret Team B'))->toBeFalse();
});

it('filters results by the viewer permissions', function () {
    [$org, $owner] = makeOrganization();
    app(CurrentOrganization::class)->set($org);
    Team::create(['name' => 'Alpha']);
    app(CurrentOrganization::class)->forget();

    // A viewer lacks teams.view, so teams never appear in their results.
    $viewer = addMember($org, Role::Viewer);
    $response = $this->actingAs($viewer)->getJson(route('search', ['q' => 'Alpha']));

    $groups = collect($response->json('groups'));
    expect($groups->contains(fn ($g) => $g['type'] === 'teams'))->toBeFalse();
});

it('returns nothing for a blank query', function () {
    [$org, $owner] = makeOrganization();

    $this->actingAs($owner)->getJson(route('search', ['q' => '']))
        ->assertOk()
        ->assertJsonPath('groups', []);
});
