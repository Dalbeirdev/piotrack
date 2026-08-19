<?php

declare(strict_types=1);

/**
 * QA §11 - Authentication live journey.
 *
 * The starter-kit tests cover the happy paths (render, authenticate, verify,
 * reset, logout). This covers what §11 asks for that they do not: unknown
 * accounts, lockout enforcement, session persistence and invalidation, the old
 * password being rejected after a reset, reset-token reuse, and what a brand new
 * signup actually gets in terms of tenant and permissions.
 *
 * Persona: Sarah Mitchell, Senior Account Executive at Acme Managed IT Services.
 */

use App\Authorization\Role;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;

const SARAH_EMAIL = 'sarah.mitchell@acme-managed-it-test.com';

const SARAH_PASSWORD = 'Philadelphia-CMMC-2026';

function sarah(): User
{
    return User::factory()->create([
        'name' => 'Sarah Mitchell',
        'email' => SARAH_EMAIL,
        'password' => Hash::make(SARAH_PASSWORD),
    ]);
}

beforeEach(fn () => RateLimiter::clear(''));

/*
|--------------------------------------------------------------------------
| Login failure paths
|--------------------------------------------------------------------------
*/

it('rejects a login for an account that does not exist', function () {
    $response = $this->post('/login', [
        'email' => 'nobody@acme-managed-it-test.com',
        'password' => SARAH_PASSWORD,
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors('email');
});

it('does not reveal whether an email is registered', function () {
    sarah();

    $known = $this->post('/login', ['email' => SARAH_EMAIL, 'password' => 'wrong-password-entirely']);
    $unknown = $this->post('/login', ['email' => 'ghost@acme-managed-it-test.com', 'password' => 'wrong-password-entirely']);

    // Identical failure for both, or an attacker can enumerate accounts.
    expect(session('errors')->get('email'))->not->toBeEmpty();
    $known->assertSessionHasErrors('email');
    $unknown->assertSessionHasErrors('email');

    expect($known->getSession()->get('errors')->get('email'))
        ->toBe($unknown->getSession()->get('errors')->get('email'));
});

it('locks the account out after five failed attempts and blocks the sixth', function () {
    $user = sarah();
    Event::fake([Lockout::class]);

    foreach (range(1, 5) as $attempt) {
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertSessionHasErrors('email');
    }

    // The sixth is refused by the throttle, not by the credential check - so even
    // the CORRECT password must be rejected while the lockout stands.
    $response = $this->post('/login', ['email' => $user->email, 'password' => SARAH_PASSWORD]);

    $this->assertGuest();
    $response->assertSessionHasErrors('email');
    expect((string) session('errors')->get('email')[0])->toContain('seconds');

    Event::assertDispatched(Lockout::class);
});

/*
|--------------------------------------------------------------------------
| Session behaviour
|--------------------------------------------------------------------------
*/

it('persists the session across subsequent requests', function () {
    $user = sarah();

    $this->post('/login', ['email' => $user->email, 'password' => SARAH_PASSWORD]);
    $this->assertAuthenticatedAs($user);

    // A second, independent request must still be authenticated.
    $this->get('/settings/password')->assertSuccessful();
    $this->assertAuthenticatedAs($user);
});

it('invalidates the session and rotates the token on logout', function () {
    $user = sarah();
    $this->actingAs($user);

    $before = session()->getId();
    $this->post('/logout')->assertRedirect('/');

    $this->assertGuest();
    expect(session()->getId())->not->toBe($before);
});

/*
|--------------------------------------------------------------------------
| Password reset - §11 requires the OLD password to stop working
|--------------------------------------------------------------------------
*/

it('rejects the old password after a reset and accepts the new one', function () {
    Notification::fake();
    $user = sarah();

    $this->post('/forgot-password', ['email' => $user->email]);

    $token = null;
    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use (&$token) {
        $token = $notification->token;

        return true;
    });

    $newPassword = 'Cherry-Hill-NJ-2026';
    $this->post('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => $newPassword,
        'password_confirmation' => $newPassword,
    ])->assertSessionHasNoErrors();

    // Old credentials must be dead.
    $this->post('/login', ['email' => $user->email, 'password' => SARAH_PASSWORD]);
    $this->assertGuest();

    // New credentials must work.
    $this->post('/login', ['email' => $user->email, 'password' => $newPassword]);
    $this->assertAuthenticatedAs($user->fresh());
});

it('refuses to reuse a password reset token', function () {
    Notification::fake();
    $user = sarah();

    $this->post('/forgot-password', ['email' => $user->email]);

    $token = null;
    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use (&$token) {
        $token = $notification->token;

        return true;
    });

    $payload = fn (string $password) => [
        'token' => $token, 'email' => $user->email,
        'password' => $password, 'password_confirmation' => $password,
    ];

    $this->post('/reset-password', $payload('First-Reset-Password-1'))->assertSessionHasNoErrors();
    $this->post('/reset-password', $payload('Second-Reset-Password-2'))->assertSessionHasErrors('email');

    // The second attempt must not have taken effect.
    $this->post('/login', ['email' => $user->email, 'password' => 'Second-Reset-Password-2']);
    $this->assertGuest();
});

it('does not disclose account existence when requesting a reset link', function () {
    Notification::fake();

    $this->post('/forgot-password', ['email' => 'ghost@acme-managed-it-test.com'])
        ->assertSessionHasNoErrors();

    Notification::assertNothingSent();
})->skip(fn () => Password::broker() === null, 'password broker unavailable');

/*
|--------------------------------------------------------------------------
| What a brand new signup actually receives
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Deactivated membership - the closest thing to a "locked account" here
|--------------------------------------------------------------------------
|
| There is no suspension flag on users, so revoking access means flipping the
| organization membership pivot off "active". Both tenant-resolving middlewares
| read through activeOrganizations(), so this asserts the revocation actually
| bites on a live request rather than only in the relationship.
*/

it('cuts off a member whose organization membership is deactivated', function () {
    [$organization, $owner] = makeOrganization('Acme Managed IT Services');
    subscribeOrganization($organization, 'professional');

    $sarah = addMember($organization, Role::SalesManager);

    // While active, she reaches the dashboard.
    $this->actingAs($sarah)->get('/dashboard')->assertSuccessful();

    $organization->members()->updateExistingPivot($sarah->id, ['status' => 'suspended']);
    $sarah = $sarah->fresh();

    expect($sarah->activeOrganizations()->count())->toBe(0);

    // She is still authenticated, but must no longer resolve into the tenant.
    $this->actingAs($sarah)->get('/dashboard')->assertRedirect(route('organizations.create'));

    // And the owner is unaffected.
    $this->actingAs($owner)->get('/dashboard')->assertSuccessful();
});

it('creates an unverified user with no tenant and keeps them off the dashboard', function () {
    $response = $this->post('/register', [
        'name' => 'Sarah Mitchell',
        'email' => SARAH_EMAIL,
        'password' => SARAH_PASSWORD,
        'password_confirmation' => SARAH_PASSWORD,
    ]);

    $user = User::where('email', SARAH_EMAIL)->firstOrFail();

    expect($user->email_verified_at)->toBeNull()
        ->and($user->current_organization_id)->toBeNull()
        ->and($user->organizations()->count())->toBe(0);

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('dashboard', absolute: false));

    // Authenticated but unverified and tenantless: the dashboard must not open.
    $this->get('/dashboard')->assertRedirect();
});
