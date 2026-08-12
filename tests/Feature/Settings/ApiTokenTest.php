<?php

use App\Models\AuditLog;
use App\Models\User;

function asConfirmedUser($test, User $user)
{
    return $test->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);
}

it('requires recent password confirmation to manage api tokens', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('api-tokens.index'))
        ->assertRedirect(route('password.confirm'));
});

it('creates a token and reveals the plaintext exactly once', function () {
    $user = User::factory()->create();

    asConfirmedUser($this, $user)
        ->post(route('api-tokens.store'), ['name' => 'Reporting script'])
        ->assertRedirect();

    expect($user->tokens()->count())->toBe(1)
        ->and($user->tokens()->first()->name)->toBe('Reporting script');

    // Flash is present on the first page view…
    $first = asConfirmedUser($this, $user)->get(route('api-tokens.index'));
    $first->assertInertia(fn ($page) => $page
        ->component('settings/api-tokens')
        ->whereNot('plainTextToken', null));

    // …and gone on the second.
    $second = asConfirmedUser($this, $user)->get(route('api-tokens.index'));
    $second->assertInertia(fn ($page) => $page->where('plainTextToken', null));

    expect(AuditLog::where('action', 'auth.api_token_created')->where('actor_id', $user->id)->exists())->toBeTrue();
});

it('authenticates api requests with a token and blocks after revocation', function () {
    $user = User::factory()->create();
    $token = $user->createToken('cli');

    $this->getJson('/api/user', ['Authorization' => 'Bearer '.$token->plainTextToken])
        ->assertOk()
        ->assertJsonPath('email', $user->email);

    asConfirmedUser($this, $user)
        ->delete(route('api-tokens.destroy', $token->accessToken->id))
        ->assertRedirect();

    $this->app->make('auth')->forgetGuards();

    $this->getJson('/api/user', ['Authorization' => 'Bearer '.$token->plainTextToken])
        ->assertUnauthorized();

    expect(AuditLog::where('action', 'auth.api_token_revoked')->where('actor_id', $user->id)->exists())->toBeTrue();
});

it('cannot revoke another user\'s token', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $token = $owner->createToken('theirs');

    asConfirmedUser($this, $intruder)
        ->delete(route('api-tokens.destroy', $token->accessToken->id))
        ->assertNotFound();

    expect($owner->tokens()->count())->toBe(1);
});

it('requires a token name', function () {
    $user = User::factory()->create();

    asConfirmedUser($this, $user)
        ->post(route('api-tokens.store'), ['name' => ''])
        ->assertSessionHasErrors('name');
});
