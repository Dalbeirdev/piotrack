<?php

use App\Authorization\Role;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use App\Services\OrganizationService;

it('creates an organization and makes the creator its owner', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('organizations.store'), ['name' => 'Acme MSP'])
        ->assertRedirect(route('dashboard'));

    $organization = Organization::firstWhere('name', 'Acme MSP');

    expect($organization)->not->toBeNull()
        ->and($user->refresh()->current_organization_id)->toBe($organization->id)
        ->and($user->roleIn($organization))->toBe(Role::Owner);

    expect(AuditLog::where('action', 'organization.created')->where('organization_id', $organization->id)->exists())->toBeTrue();
});

it('sends a verified user with no organization to create one', function () {
    $user = User::factory()->create(); // no organization

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('organizations.create'));
});

it('lets a member switch between their organizations', function () {
    [$orgA, $owner] = makeOrganization('A');
    $orgB = app(OrganizationService::class)->create($owner, 'B');

    // create() set current to B; switch back to A.
    $this->actingAs($owner->refresh())
        ->post(route('organizations.switch', $orgA->id))
        ->assertRedirect();

    expect($owner->refresh()->current_organization_id)->toBe($orgA->id);
});

it('forbids switching into an organization you do not belong to', function () {
    [$orgA, $ownerA] = makeOrganization('A');
    [$orgB] = makeOrganization('B');

    $this->actingAs($ownerA)
        ->post(route('organizations.switch', $orgB->id))
        ->assertNotFound();

    expect($ownerA->refresh()->current_organization_id)->toBe($orgA->id);
});

it('updates the organization name', function () {
    [$org, $owner] = makeOrganization('Old Name');

    $this->actingAs($owner)
        ->patch(route('organization.update'), ['name' => 'New Name'])
        ->assertRedirect();

    expect($org->refresh()->name)->toBe('New Name');
});

it('deletes an organization only when the name is typed correctly', function () {
    [$org, $owner] = makeOrganization('Delete Me');

    // Wrong confirmation is rejected.
    $this->actingAs($owner)
        ->delete(route('organization.destroy'), ['name' => 'wrong'])
        ->assertSessionHasErrors('name');
    expect($org->refresh()->trashed())->toBeFalse();

    // Correct confirmation soft-deletes and clears current org.
    $this->actingAs($owner)
        ->delete(route('organization.destroy'), ['name' => 'Delete Me'])
        ->assertRedirect(route('dashboard'));

    expect($org->refresh()->trashed())->toBeTrue()
        ->and($owner->refresh()->current_organization_id)->toBeNull();
});
