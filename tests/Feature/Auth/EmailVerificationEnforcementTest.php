<?php

use App\Models\User;

it('redirects unverified users away from the dashboard', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('verification.notice'));
});

it('allows verified users with an organization onto the dashboard', function () {
    [, $owner] = makeOrganization();

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertOk();
});
