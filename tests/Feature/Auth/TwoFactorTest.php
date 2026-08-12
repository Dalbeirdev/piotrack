<?php

use App\Models\AuditLog;
use App\Models\User;
use PragmaRX\Google2FA\Google2FA;

function withConfirmedPassword($test, User $user)
{
    return $test->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);
}

function createTwoFactorUser(array $recoveryCodes = ['AAAAA-BBBBB', 'CCCCC-DDDDD']): array
{
    $secret = app(Google2FA::class)->generateSecretKey();

    $user = User::factory()->create();
    $user->forceFill([
        'two_factor_secret' => $secret,
        'two_factor_recovery_codes' => $recoveryCodes,
        'two_factor_confirmed_at' => now(),
    ])->save();

    return [$user, $secret];
}

it('enrolls in two-factor authentication end to end', function () {
    $user = User::factory()->create();

    withConfirmedPassword($this, $user)
        ->post(route('two-factor.enable'))
        ->assertRedirect();

    $user->refresh();
    expect($user->two_factor_secret)->not->toBeNull()
        ->and($user->hasEnabledTwoFactor())->toBeFalse();

    $code = app(Google2FA::class)->getCurrentOtp($user->two_factor_secret);

    withConfirmedPassword($this, $user)
        ->post(route('two-factor.confirm'), ['code' => $code])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $user->refresh();
    expect($user->hasEnabledTwoFactor())->toBeTrue()
        ->and($user->two_factor_recovery_codes)->toHaveCount(8);

    expect(AuditLog::where('action', 'auth.two_factor_enabled')->where('actor_id', $user->id)->exists())->toBeTrue();
});

it('rejects an invalid confirmation code', function () {
    $user = User::factory()->create();

    withConfirmedPassword($this, $user)->post(route('two-factor.enable'));

    withConfirmedPassword($this, $user)
        ->post(route('two-factor.confirm'), ['code' => '000000'])
        ->assertSessionHasErrors('code');

    expect($user->refresh()->hasEnabledTwoFactor())->toBeFalse();
});

it('requires recent password confirmation for two-factor management', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('two-factor.show'))
        ->assertRedirect(route('password.confirm'));
});

it('redirects two-factor users to the challenge instead of logging in', function () {
    [$user] = createTwoFactorUser();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('two-factor.challenge'));
    $this->assertGuest();
});

it('completes login with a valid authenticator code', function () {
    [$user, $secret] = createTwoFactorUser();

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);

    $this->get(route('two-factor.challenge'))->assertOk();

    $this->post(route('two-factor.challenge'), [
        'code' => app(Google2FA::class)->getCurrentOtp($secret),
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
});

it('rejects an invalid challenge code and stays logged out', function () {
    [$user] = createTwoFactorUser();

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);

    $this->post(route('two-factor.challenge'), ['code' => '000000'])
        ->assertSessionHasErrors('code');

    $this->assertGuest();
});

it('accepts a recovery code once and consumes it', function () {
    [$user] = createTwoFactorUser(['AAAAA-BBBBB', 'CCCCC-DDDDD']);

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);

    $this->post(route('two-factor.challenge'), ['recovery_code' => 'AAAAA-BBBBB'])
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
    expect($user->refresh()->two_factor_recovery_codes)->toBe(['CCCCC-DDDDD']);

    // A consumed code no longer works.
    $this->post('/logout');
    $this->post('/login', ['email' => $user->email, 'password' => 'password']);

    $this->post(route('two-factor.challenge'), ['recovery_code' => 'AAAAA-BBBBB'])
        ->assertSessionHasErrors('code');
    $this->assertGuest();
});

it('redirects to login when visiting the challenge without a pending login', function () {
    $this->get(route('two-factor.challenge'))->assertRedirect(route('login'));
});

it('regenerates recovery codes', function () {
    [$user] = createTwoFactorUser(['AAAAA-BBBBB', 'CCCCC-DDDDD']);

    withConfirmedPassword($this, $user)
        ->post(route('two-factor.recovery-codes'))
        ->assertRedirect();

    $codes = $user->refresh()->two_factor_recovery_codes;
    expect($codes)->toHaveCount(8)->not->toContain('AAAAA-BBBBB');

    expect(AuditLog::where('action', 'auth.recovery_codes_regenerated')->where('actor_id', $user->id)->exists())->toBeTrue();
});

it('disables two-factor authentication', function () {
    [$user] = createTwoFactorUser();

    withConfirmedPassword($this, $user)
        ->delete(route('two-factor.disable'))
        ->assertRedirect();

    $user->refresh();
    expect($user->two_factor_secret)->toBeNull()
        ->and($user->two_factor_recovery_codes)->toBeNull()
        ->and($user->hasEnabledTwoFactor())->toBeFalse();

    expect(AuditLog::where('action', 'auth.two_factor_disabled')->where('actor_id', $user->id)->exists())->toBeTrue();
});
