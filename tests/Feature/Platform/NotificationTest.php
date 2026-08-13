<?php

use App\Models\NotificationPreference;
use App\Models\User;
use App\Notifications\PaymentFailedNotification;
use Illuminate\Support\Facades\Notification;

it('delivers a platform notification via database and mail by default', function () {
    $user = User::factory()->create();

    $user->notify(new PaymentFailedNotification('Acme'));

    expect($user->notifications()->count())->toBe(1)
        ->and($user->unreadNotifications()->count())->toBe(1);

    $stored = $user->notifications()->first();
    expect($stored->data['category'])->toBe('billing')
        ->and($stored->data['title'])->toBe('Payment failed');
});

it('resolves channels from user preferences', function () {
    $user = User::factory()->create();

    // Default: email on.
    expect((new PaymentFailedNotification('Acme'))->via($user))->toContain('mail');

    // Opt out of billing email.
    NotificationPreference::create(['user_id' => $user->id, 'category' => 'billing', 'channel' => 'email', 'enabled' => false]);
    $user->load('notificationPreferences');

    expect((new PaymentFailedNotification('Acme'))->via($user))
        ->toContain('database')
        ->not->toContain('mail');
});

it('sends payment-failure notifications to organization owners on past_due', function () {
    Notification::fake();
    [$org, $owner] = makeOrganization();
    subscribeOrganization($org, 'growth');

    app(App\Services\SubscriptionService::class)->markPastDue($org->activeSubscription());

    Notification::assertSentTo($owner, PaymentFailedNotification::class);
});

it('notifies owners when a member joins', function () {
    Notification::fake();
    [$org, $owner] = makeOrganization();
    $token = app(App\Services\OrganizationService::class)
        ->invite($org, $owner, 'joiner@example.com', App\Authorization\Role::Viewer)['token'];
    $joiner = User::factory()->create(['email' => 'joiner@example.com']);

    $this->actingAs($joiner)->post(route('invitations.accept', $token));

    Notification::assertSentTo($owner, App\Notifications\MemberJoinedNotification::class);
});

it('marks notifications as read', function () {
    $user = User::factory()->create();
    $user->notify(new PaymentFailedNotification('Acme'));
    $id = $user->notifications()->first()->id;

    $this->actingAs($user)->post(route('notifications.read', $id))->assertRedirect();
    expect($user->unreadNotifications()->count())->toBe(0);
});

it('marks all notifications as read', function () {
    $user = User::factory()->create();
    $user->notify(new PaymentFailedNotification('A'));
    $user->notify(new PaymentFailedNotification('B'));

    $this->actingAs($user)->post(route('notifications.read-all'))->assertRedirect();
    expect($user->unreadNotifications()->count())->toBe(0);
});

it('saves a notification preference', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('notifications.preferences'), ['category' => 'members', 'channel' => 'email', 'enabled' => false])
        ->assertRedirect();

    expect($user->wantsChannel('members', 'email'))->toBeFalse();
});

it('cannot disable security notifications', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('notifications.preferences'), ['category' => 'security', 'channel' => 'email', 'enabled' => false]);

    expect($user->wantsChannel('security', 'email'))->toBeTrue();
});
