<?php

use App\Authorization\Permission;
use App\Authorization\Role;
use App\Authorization\RolePermissions;

it('grants an owner every organization permission but no platform-staff ability', function () {
    $owner = RolePermissions::for(Role::Owner->value);

    // Platform administration and impersonation are held only through
    // users.platform_role, never by an organization role - see RolePermissions.
    $platformOnly = [Permission::AdminPlatform->value, Permission::AdminImpersonate->value];
    $organizationPermissions = array_values(array_diff(Permission::values(), $platformOnly));

    expect($owner)->toEqualCanonicalizing($organizationPermissions)
        ->and($owner)->not->toContain(Permission::AdminPlatform->value)
        ->and($owner)->not->toContain(Permission::AdminImpersonate->value);
});

it('grants an admin everything except deleting the organization', function () {
    $admin = RolePermissions::for(Role::Admin->value);

    expect($admin)->not->toContain(Permission::OrganizationDelete->value)
        ->and($admin)->toContain(Permission::MembersInvite->value)
        ->and($admin)->toContain(Permission::TeamsManage->value);
});

it('limits a viewer to read-only capabilities', function () {
    $permissions = RolePermissions::for(Role::Viewer->value);

    expect($permissions)
        ->toContain(Permission::OrganizationView->value)
        ->toContain(Permission::FilesView->value)
        ->toContain(Permission::CrmContactRead->value)
        ->not->toContain(Permission::CrmContactCreate->value)
        ->not->toContain(Permission::CrmContactDelete->value)
        ->not->toContain(Permission::MembersInvite->value);
});

it('resolves permissions against the current organization only', function () {
    [$orgA] = makeOrganization('A');
    [$orgB] = makeOrganization('B');

    $user = addMember($orgA, Role::Analyst);

    expect($user->permissionsIn($orgA))->toContain(Permission::AuditView->value)
        ->and($user->permissionsIn($orgB))->toBe([]); // not a member of B
});

dataset('role access to members page', [
    'owner can view members' => [Role::Owner, true],
    'admin can view members' => [Role::Admin, true],
    'marketing manager can view members' => [Role::MarketingManager, true],
    'analyst cannot view members' => [Role::Analyst, false],
    'viewer cannot view members' => [Role::Viewer, false],
    'marketing user cannot view members' => [Role::MarketingUser, false],
]);

it('enforces the members.view permission on the backend', function (Role $role, bool $allowed) {
    [$org] = makeOrganization();
    $user = addMember($org, $role);

    $response = $this->actingAs($user)->get(route('members.index'));

    $allowed ? $response->assertOk() : $response->assertForbidden();
})->with('role access to members page');

it('forbids a viewer from inviting members', function () {
    [$org] = makeOrganization();
    $viewer = addMember($org, Role::Viewer);

    $this->actingAs($viewer)
        ->post(route('invitations.store'), ['email' => 'x@example.com', 'role' => 'viewer'])
        ->assertForbidden();
});

it('allows only owners to delete an organization', function () {
    [$org] = makeOrganization('Deletable');
    $admin = addMember($org, Role::Admin);

    $this->actingAs($admin)
        ->delete(route('organization.destroy'), ['name' => 'Deletable'])
        ->assertForbidden();
});

it('lets a platform super admin bypass organization permissions', function () {
    [$org] = makeOrganization();

    $superAdmin = addMember($org, Role::Viewer); // low org role...
    $superAdmin->forceFill(['platform_role' => Role::PlatformSuperAdmin->value])->save();

    // ...but the platform super admin bypass grants access anyway.
    $this->actingAs($superAdmin->refresh())
        ->get(route('members.index'))
        ->assertOk();
});

it('shares the resolved permission list to the frontend', function () {
    [$org, $owner] = makeOrganization();

    $this->actingAs($owner)->get(route('dashboard'))->assertInertia(fn ($page) => $page
        ->where('auth.role', Role::Owner->value)
        ->where('auth.permissions', fn ($permissions) => collect($permissions)->contains(Permission::MembersInvite->value)),
    );
});
