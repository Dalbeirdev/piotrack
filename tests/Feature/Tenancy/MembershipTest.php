<?php

use App\Authorization\Role;
use App\Models\AuditLog;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\OrganizationInvitation;
use App\Services\OrganizationService;
use Illuminate\Support\Facades\Notification;

it('invites a member and emails them', function () {
    Notification::fake();
    [$org, $owner] = makeOrganization();

    $this->actingAs($owner)
        ->post(route('invitations.store'), ['email' => 'new@example.com', 'role' => Role::SalesManager->value])
        ->assertRedirect();

    $invitation = Invitation::withoutTenantScope()->firstWhere('email', 'new@example.com');
    expect($invitation)->not->toBeNull()
        ->and($invitation->role)->toBe(Role::SalesManager->value)
        ->and($invitation->organization_id)->toBe($org->id);

    Notification::assertSentOnDemand(OrganizationInvitation::class);
    expect(AuditLog::where('action', 'member.invited')->exists())->toBeTrue();
});

it('lets the invited user accept and join with the invited role', function () {
    [$org, $owner] = makeOrganization();
    $token = app(OrganizationService::class)
        ->invite($org, $owner, 'invitee@example.com', Role::Analyst)['token'];

    $invitee = User::factory()->create(['email' => 'invitee@example.com']);

    $this->actingAs($invitee)
        ->post(route('invitations.accept', $token))
        ->assertRedirect(route('dashboard'));

    expect($invitee->refresh()->roleIn($org))->toBe(Role::Analyst)
        ->and($invitee->current_organization_id)->toBe($org->id);
    expect(AuditLog::where('action', 'member.invitation_accepted')->exists())->toBeTrue();
});

it('refuses acceptance from a different email', function () {
    [$org, $owner] = makeOrganization();
    $token = app(OrganizationService::class)
        ->invite($org, $owner, 'intended@example.com', Role::Viewer)['token'];

    $someoneElse = User::factory()->create(['email' => 'someone.else@example.com']);

    $this->actingAs($someoneElse)
        ->post(route('invitations.accept', $token))
        ->assertForbidden();

    expect($someoneElse->refresh()->belongsToOrganization($org))->toBeFalse();
});

it('rejects an expired invitation token', function () {
    [$org] = makeOrganization();
    $plain = 'plain-token-value';
    Invitation::factory()->expired()->create([
        'organization_id' => $org->id,
        'email' => 'late@example.com',
        'token' => hash('sha256', $plain),
    ]);

    $user = User::factory()->create(['email' => 'late@example.com']);

    $this->actingAs($user)->get(route('invitations.show', $plain))
        ->assertInertia(fn ($page) => $page->component('invitations/accept')->where('valid', false));
});

it('revokes a pending invitation', function () {
    [$org, $owner] = makeOrganization();
    $invitation = Invitation::factory()->create(['organization_id' => $org->id]);

    $this->actingAs($owner)
        ->delete(route('invitations.destroy', $invitation->id))
        ->assertRedirect();

    expect(Invitation::withoutTenantScope()->find($invitation->id))->toBeNull();
    expect(AuditLog::where('action', 'member.invitation_revoked')->exists())->toBeTrue();
});

it('resends an invitation with a fresh token', function () {
    Notification::fake();
    [$org, $owner] = makeOrganization();
    $invitation = Invitation::factory()->create(['organization_id' => $org->id]);
    $originalToken = $invitation->token;

    $this->actingAs($owner)
        ->post(route('invitations.resend', $invitation->id))
        ->assertRedirect();

    expect($invitation->refresh()->token)->not->toBe($originalToken);
    Notification::assertSentOnDemand(OrganizationInvitation::class);
});

it('changes a member role', function () {
    [$org, $owner] = makeOrganization();
    $member = addMember($org, Role::Viewer);

    $this->actingAs($owner)
        ->patch(route('members.role', $member->id), ['role' => Role::SalesManager->value])
        ->assertRedirect();

    expect($member->refresh()->roleIn($org))->toBe(Role::SalesManager);
    expect(AuditLog::where('action', 'member.role_changed')->exists())->toBeTrue();
});

it('protects the last owner from being demoted, removed, or deactivated', function () {
    [$org, $owner] = makeOrganization();

    // Demote
    $this->actingAs($owner)
        ->patch(route('members.role', $owner->id), ['role' => Role::Viewer->value])
        ->assertSessionHasErrors('role');

    // Remove
    $this->actingAs($owner)
        ->delete(route('members.destroy', $owner->id))
        ->assertSessionHasErrors('member');

    // Deactivate
    $this->actingAs($owner)
        ->patch(route('members.deactivate', $owner->id))
        ->assertSessionHasErrors('member');

    expect($owner->refresh()->roleIn($org))->toBe(Role::Owner);
});

it('removes a member and clears their current organization', function () {
    [$org, $owner] = makeOrganization();
    $member = addMember($org, Role::Admin);

    $this->actingAs($owner)
        ->delete(route('members.destroy', $member->id))
        ->assertRedirect();

    expect($member->refresh()->belongsToOrganization($org))->toBeFalse()
        ->and($member->current_organization_id)->toBeNull();
    expect(AuditLog::where('action', 'member.removed')->exists())->toBeTrue();
});

it('deactivates and reactivates a member', function () {
    [$org, $owner] = makeOrganization();
    $member = addMember($org, Role::Admin);

    $this->actingAs($owner)->patch(route('members.deactivate', $member->id))->assertRedirect();
    expect($member->refresh()->belongsToOrganization($org))->toBeFalse(); // deactivated = not active

    $this->actingAs($owner)->patch(route('members.reactivate', $member->id))->assertRedirect();
    expect($member->refresh()->belongsToOrganization($org))->toBeTrue();
});
