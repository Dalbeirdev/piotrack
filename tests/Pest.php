<?php

use App\Authorization\Role;
use App\Models\Organization;
use App\Models\User;
use App\Services\OrganizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/*
|--------------------------------------------------------------------------
| Tenancy test helpers (Stage 2)
|--------------------------------------------------------------------------
*/

/**
 * Create an organization owned by a fresh user.
 *
 * @return array{0: Organization, 1: User}
 */
function makeOrganization(string $name = 'Test Org'): array
{
    $owner = User::factory()->create();
    $organization = app(OrganizationService::class)->create($owner, $name);

    return [$organization, $owner->refresh()];
}

/**
 * Add a member with the given role to an organization and make it their
 * current organization.
 */
function addMember(Organization $organization, Role $role): User
{
    $user = User::factory()->create();

    $organization->members()->attach($user->id, [
        'role' => $role->value,
        'status' => 'active',
        'joined_at' => now(),
    ]);

    $user->forceFill(['current_organization_id' => $organization->id])->save();

    return $user->refresh();
}
