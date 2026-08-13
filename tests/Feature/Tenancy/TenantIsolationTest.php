<?php

use App\Authorization\Role;
use App\Models\Invitation;
use App\Models\Team;
use App\Support\CurrentOrganization;

/*
 * The cross-tenant access suite (TEN-006). Tenant A must never reach Tenant B's
 * data — through the ORM scope, route-model binding, or list endpoints.
 */

it('scopes tenant-owned queries to the current organization', function () {
    [$orgA] = makeOrganization('A');
    [$orgB] = makeOrganization('B');

    app(CurrentOrganization::class)->set($orgA);
    Team::create(['name' => 'Alpha team']);

    app(CurrentOrganization::class)->set($orgB);
    Team::create(['name' => 'Bravo team']);

    // Under B, only B's team is visible.
    expect(Team::count())->toBe(1)
        ->and(Team::first()->name)->toBe('Bravo team');

    app(CurrentOrganization::class)->set($orgA);
    expect(Team::count())->toBe(1)
        ->and(Team::first()->name)->toBe('Alpha team');
});

it('stamps organization_id on create and forbids changing it', function () {
    [$orgA] = makeOrganization('A');
    [$orgB] = makeOrganization('B');

    app(CurrentOrganization::class)->set($orgA);
    $team = Team::create(['name' => 'Alpha']);
    expect($team->organization_id)->toBe($orgA->id);

    $team->organization_id = $orgB->id;
    expect(fn () => $team->save())->toThrow(RuntimeException::class);
});

it('withoutTenantScope crosses tenants deliberately', function () {
    [$orgA] = makeOrganization('A');
    [$orgB] = makeOrganization('B');

    app(CurrentOrganization::class)->set($orgA);
    $team = Team::create(['name' => 'Alpha']);

    app(CurrentOrganization::class)->set($orgB);
    expect(Team::find($team->id))->toBeNull()
        ->and(Team::withoutTenantScope()->find($team->id))->not->toBeNull();
});

it('blocks route-model binding across tenants (teams)', function () {
    [$orgA, $ownerA] = makeOrganization('A');
    [$orgB, $ownerB] = makeOrganization('B');

    app(CurrentOrganization::class)->set($orgB);
    $teamB = Team::create(['name' => 'Bravo']);
    app(CurrentOrganization::class)->forget();

    // Owner of A tries to delete a team belonging to B → 404 (not found in tenant).
    $this->actingAs($ownerA)
        ->delete(route('teams.destroy', $teamB->id))
        ->assertNotFound();

    expect(Team::withoutTenantScope()->find($teamB->id))->not->toBeNull();
});

it('blocks route-model binding across tenants (invitations)', function () {
    [$orgA, $ownerA] = makeOrganization('A');
    [$orgB] = makeOrganization('B');

    $invitationB = Invitation::factory()->create([
        'organization_id' => $orgB->id,
        'role' => Role::Viewer->value,
    ]);

    $this->actingAs($ownerA)
        ->delete(route('invitations.destroy', $invitationB->id))
        ->assertNotFound();
});

it('never leaks another tenant members list', function () {
    [$orgA, $ownerA] = makeOrganization('A');
    [$orgB] = makeOrganization('B');
    $bobInB = addMember($orgB, Role::Admin);

    $response = $this->actingAs($ownerA)->get(route('members.index'));

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('settings/members')
        ->where('members', fn ($members) => collect($members)->doesntContain('email', $bobInB->email)),
    );
});

it('scopes the audit log viewer to the current organization', function () {
    [$orgA, $ownerA] = makeOrganization('A');
    [$orgB, $ownerB] = makeOrganization('B');

    // Org A's creation logged organization.created (+ subscription.trial_started).
    $response = $this->actingAs($ownerA)->get(route('audit.index'));

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->where('logs.data', fn ($logs) => collect($logs)->isNotEmpty()
            // Any actor shown is org A's owner (or a system event with no actor).
            && collect($logs)->every(fn ($log) => ($log['actor']['email'] ?? $ownerA->email) === $ownerA->email)),
    );

    // B's owner never appears in A's audit log.
    $response->assertInertia(fn ($page) => $page
        ->where('logs.data', fn ($logs) => collect($logs)->doesntContain(
            fn ($log) => ($log['actor']['email'] ?? null) === $ownerB->email,
        )),
    );
});
