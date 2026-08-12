<?php

use App\Models\User;

it('rejects passwords shorter than 12 characters at registration', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'weak-password@example.com',
        'password' => 'short-pw',
        'password_confirmation' => 'short-pw',
    ]);

    $response->assertSessionHasErrors('password');
    $this->assertGuest();
});

it('rejects short passwords when updating from settings', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->put('/settings/password', [
        'current_password' => 'password',
        'password' => 'short-pw',
        'password_confirmation' => 'short-pw',
    ])->assertSessionHasErrors('password');
});
