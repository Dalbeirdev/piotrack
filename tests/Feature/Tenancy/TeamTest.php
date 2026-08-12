<?php

use App\Authorization\Role;
use App\Models\AuditLog;
use App\Models\Team;

it('creates a team scoped to the current organization', function () {
    [$org, $owner] = makeOrganization();

    $this->actingAs($owner)
        ->post(route('teams.store'), ['name' => 'Onboarding pod'])
        ->assertRedirect();

    $team = Team::withoutTenantScope()->firstWhere('name', 'Onboarding pod');
    expect($team)->not->toBeNull()
        ->and($team->organization_id)->toBe($org->id);
    expect(AuditLog::where('action', 'team.created')->exists())->toBeTrue();
});

it('adds and removes organization members on a team', function () {
    [$org, $owner] = makeOrganization();
    $member = addMember($org, Role::MarketingUser);

    $this->actingAs($owner)->post(route('teams.store'), ['name' => 'Pod']);
    $team = Team::withoutTenantScope()->firstWhere('name', 'Pod');

    $this->actingAs($owner)
        ->post(route('teams.members.add', $team->id), ['user_id' => $member->id])
        ->assertRedirect();
    expect($team->members()->whereKey($member->id)->exists())->toBeTrue();

    $this->actingAs($owner)
        ->delete(route('teams.members.remove', [$team->id, $member->id]))
        ->assertRedirect();
    expect($team->members()->whereKey($member->id)->exists())->toBeFalse();
});

it('refuses to add a non-member to a team', function () {
    [$org, $owner] = makeOrganization();
    [$otherOrg] = makeOrganization('Other');
    $outsider = addMember($otherOrg, Role::Admin);

    $this->actingAs($owner)->post(route('teams.store'), ['name' => 'Pod']);
    $team = Team::withoutTenantScope()->firstWhere('name', 'Pod');

    $this->actingAs($owner)
        ->post(route('teams.members.add', $team->id), ['user_id' => $outsider->id])
        ->assertSessionHasErrors('user_id');
});

it('deletes a team', function () {
    [$org, $owner] = makeOrganization();
    $this->actingAs($owner)->post(route('teams.store'), ['name' => 'Pod']);
    $team = Team::withoutTenantScope()->firstWhere('name', 'Pod');

    $this->actingAs($owner)->delete(route('teams.destroy', $team->id))->assertRedirect();

    expect(Team::withoutTenantScope()->find($team->id))->toBeNull();
    expect(AuditLog::where('action', 'team.deleted')->exists())->toBeTrue();
});

it('forbids a viewer from managing teams', function () {
    [$org] = makeOrganization();
    $viewer = addMember($org, Role::Viewer);

    $this->actingAs($viewer)
        ->post(route('teams.store'), ['name' => 'Nope'])
        ->assertForbidden();
});
