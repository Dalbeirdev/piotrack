<?php

use App\Authorization\Role;
use App\Billing\Entitlements;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Services\OrganizationService;
use App\Services\SubscriptionService;
use Database\Seeders\PlanSeeder;
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
    // Seed the plan catalog so organization creation (which starts a trial) and
    // all billing/entitlement resolution have plans available.
    ->beforeEach(fn () => test()->seed(PlanSeeder::class))
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

/**
 * Subscribe an organization to a plan (immediate manual checkout).
 */
function subscribeOrganization(Organization $organization, string $planCode, string $interval = 'monthly'): void
{
    $plan = Plan::where('code', $planCode)->firstOrFail();
    app(SubscriptionService::class)->checkout($organization, $plan, $interval, 1, null);
    app(Entitlements::class)->forget($organization);
}
