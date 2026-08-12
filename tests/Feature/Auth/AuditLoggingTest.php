<?php

use App\Models\AuditLog;
use App\Models\User;

it('records successful logins in the audit trail', function () {
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);

    $logs = AuditLog::where('action', 'auth.login')->where('actor_id', $user->id)->get();

    // Exactly one row: guards against double listener registration
    // (auto-discovery + manual subscribe), found during Stage 1 QA.
    expect($logs)->toHaveCount(1)
        ->and($logs->first()->ip_address)->not->toBeNull();
});

it('records failed logins with the attempted email and no actor', function () {
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);

    $log = AuditLog::where('action', 'auth.login_failed')->first();
    expect($log)->not->toBeNull()
        ->and($log->context['email'])->toBe($user->email)
        ->and($log->context)->not->toHaveKey('password');
});

it('records a lockout after repeated failures', function () {
    $user = User::factory()->create();

    for ($i = 0; $i < 6; $i++) {
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);
    }

    expect(AuditLog::where('action', 'auth.lockout')->exists())->toBeTrue();
});

it('records logout events', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/logout');

    expect(AuditLog::where('action', 'auth.logout')->where('actor_id', $user->id)->count())->toBe(1);
});

it('records registration events', function () {
    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'audit-registration@example.com',
        'password' => 'a-long-secure-password',
        'password_confirmation' => 'a-long-secure-password',
    ]);

    expect(AuditLog::where('action', 'auth.registered')->count())->toBe(1);
});

it('records password changes from settings', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->put('/settings/password', [
        'current_password' => 'password',
        'password' => 'a-long-secure-password',
        'password_confirmation' => 'a-long-secure-password',
    ])->assertSessionHasNoErrors();

    expect(AuditLog::where('action', 'auth.password_changed')->where('actor_id', $user->id)->exists())->toBeTrue();
});
