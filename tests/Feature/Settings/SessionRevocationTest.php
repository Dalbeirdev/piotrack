<?php

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('logs out other browser sessions with a valid password', function () {
    $user = User::factory()->create();

    DB::table('sessions')->insert([
        'id' => 'other-session-id',
        'user_id' => $user->id,
        'ip_address' => '10.0.0.9',
        'user_agent' => 'Other Browser',
        'payload' => base64_encode(serialize([])),
        'last_activity' => now()->timestamp,
    ]);

    $this->actingAs($user)
        ->delete(route('sessions.destroy-others'), ['password' => 'password'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(DB::table('sessions')->where('id', 'other-session-id')->exists())->toBeFalse();
    expect(AuditLog::where('action', 'auth.other_sessions_revoked')->where('actor_id', $user->id)->exists())->toBeTrue();
});

it('requires the correct password to revoke other sessions', function () {
    $user = User::factory()->create();

    DB::table('sessions')->insert([
        'id' => 'other-session-id',
        'user_id' => $user->id,
        'ip_address' => '10.0.0.9',
        'user_agent' => 'Other Browser',
        'payload' => base64_encode(serialize([])),
        'last_activity' => now()->timestamp,
    ]);

    $this->actingAs($user)
        ->delete(route('sessions.destroy-others'), ['password' => 'wrong-password'])
        ->assertSessionHasErrors('password');

    expect(DB::table('sessions')->where('id', 'other-session-id')->exists())->toBeTrue();
});
