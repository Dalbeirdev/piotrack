<?php

namespace App\Services;

use App\Authorization\Role;
use App\Billing\Entitlements;
use App\Billing\Limit;
use App\Billing\PlanCatalog;
use App\Billing\UsageMeter;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Central home for organization/membership business rules (Master Prompt §15:
 * a module is a capability, not a page). Every state change records an audit
 * event and enforces the "an organization always keeps at least one Owner" rule.
 */
class OrganizationService
{
    public function __construct(
        private AuditLogger $audit,
        private SubscriptionService $subscriptions,
        private UsageMeter $usage,
    ) {}

    /**
     * Create an organization and make the creator its Owner. Sets it as the
     * creator's current organization and starts a trial on the default plan.
     */
    public function create(User $user, string $name): Organization
    {
        return DB::transaction(function () use ($user, $name) {
            $organization = Organization::create([
                'name' => $name,
                'owner_id' => $user->id,
            ]);

            $organization->members()->attach($user->id, [
                'role' => Role::Owner->value,
                'status' => 'active',
                'joined_at' => now(),
            ]);

            $user->forceFill(['current_organization_id' => $organization->id])->save();

            $this->audit->log(
                'organization.created',
                context: ['name' => $organization->name],
                resourceType: 'organization',
                resourceId: (string) $organization->id,
                organizationId: $organization->id,
            );

            // Start a trial on the default plan when the catalog is available.
            $trialPlan = Plan::where('code', PlanCatalog::DEFAULT_TRIAL_PLAN)->first();
            if ($trialPlan !== null) {
                $this->subscriptions->startTrial($organization, $trialPlan);
            }

            return $organization;
        });
    }

    public function update(Organization $organization, string $name): Organization
    {
        $organization->update(['name' => $name]);

        $this->audit->log(
            'organization.updated',
            context: ['name' => $name],
            resourceType: 'organization',
            resourceId: (string) $organization->id,
            organizationId: $organization->id,
        );

        return $organization;
    }

    public function delete(Organization $organization): void
    {
        DB::transaction(function () use ($organization) {
            // Clear the current-org pointer for anyone parked on this tenant.
            User::where('current_organization_id', $organization->id)
                ->update(['current_organization_id' => null]);

            $this->audit->log(
                'organization.deleted',
                context: ['name' => $organization->name],
                resourceType: 'organization',
                resourceId: (string) $organization->id,
                organizationId: $organization->id,
            );

            $organization->delete();
        });
    }

    /**
     * Set the user's active organization (must be an active member).
     */
    public function switchTo(User $user, Organization $organization): void
    {
        if (! $user->belongsToOrganization($organization)) {
            abort(404);
        }

        $user->forceFill(['current_organization_id' => $organization->id])->save();
    }

    // ---------------------------------------------------------------------
    // Invitations
    // ---------------------------------------------------------------------

    /**
     * @return array{invitation: Invitation, token: string}
     */
    public function invite(Organization $organization, User $inviter, string $email, Role $role): array
    {
        $email = Str::lower($email);

        if ($organization->members()->where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => __('That person is already a member of this organization.'),
            ]);
        }

        // Enforce the plan's member seat limit (ENTL-007). Seats consumed =
        // active members + pending invitations.
        if (! $this->usage->withinLimit($organization, Limit::Members)) {
            $limit = app(Entitlements::class)->limit($organization, Limit::Members);

            throw ValidationException::withMessages([
                'email' => __('Your plan is limited to :n members. Upgrade to invite more.', ['n' => $limit]),
            ]);
        }

        $token = Str::random(64);

        // Refresh any existing pending invite for this email rather than
        // stacking duplicates.
        $organization->invitations()
            ->where('email', $email)
            ->whereNull('accepted_at')
            ->delete();

        $invitation = $organization->invitations()->create([
            'email' => $email,
            'role' => $role->value,
            'token' => hash('sha256', $token),
            'invited_by' => $inviter->id,
            'expires_at' => now()->addDays(7),
        ]);

        $this->audit->log(
            'member.invited',
            context: ['email' => $email, 'role' => $role->value],
            resourceType: 'invitation',
            resourceId: (string) $invitation->id,
            organizationId: $organization->id,
        );

        return ['invitation' => $invitation, 'token' => $token];
    }

    /**
     * @return array{invitation: Invitation, token: string}
     */
    public function resendInvitation(Invitation $invitation): array
    {
        $token = Str::random(64);

        $invitation->update([
            'token' => hash('sha256', $token),
            'expires_at' => now()->addDays(7),
        ]);

        $this->audit->log(
            'member.invitation_resent',
            context: ['email' => $invitation->email],
            resourceType: 'invitation',
            resourceId: (string) $invitation->id,
            organizationId: $invitation->organization_id,
        );

        return ['invitation' => $invitation, 'token' => $token];
    }

    public function revokeInvitation(Invitation $invitation): void
    {
        $this->audit->log(
            'member.invitation_revoked',
            context: ['email' => $invitation->email],
            resourceType: 'invitation',
            resourceId: (string) $invitation->id,
            organizationId: $invitation->organization_id,
        );

        $invitation->delete();
    }

    /**
     * Accept an invitation for the given user. Assumes the caller has verified
     * the user's email matches the invitation.
     */
    public function acceptInvitation(Invitation $invitation, User $user): Organization
    {
        return DB::transaction(function () use ($invitation, $user) {
            $organization = $invitation->organization()->firstOrFail();

            $organization->members()->syncWithoutDetaching([
                $user->id => [
                    'role' => $invitation->role,
                    'status' => 'active',
                    'joined_at' => now(),
                ],
            ]);

            $invitation->update(['accepted_at' => now()]);
            $user->forceFill(['current_organization_id' => $organization->id])->save();

            $this->audit->log(
                'member.invitation_accepted',
                context: ['email' => $invitation->email, 'role' => $invitation->role],
                actorId: $user->id,
                resourceType: 'invitation',
                resourceId: (string) $invitation->id,
                organizationId: $organization->id,
            );

            return $organization;
        });
    }

    // ---------------------------------------------------------------------
    // Membership
    // ---------------------------------------------------------------------

    public function changeMemberRole(Organization $organization, User $member, Role $role): void
    {
        $current = $member->roleIn($organization);

        if ($current === Role::Owner && $role !== Role::Owner && $this->isLastOwner($organization, $member)) {
            throw ValidationException::withMessages([
                'role' => __('An organization must keep at least one Owner.'),
            ]);
        }

        $organization->members()->updateExistingPivot($member->id, ['role' => $role->value]);

        $this->audit->log(
            'member.role_changed',
            context: ['user_id' => $member->id, 'role' => $role->value],
            resourceType: 'user',
            resourceId: (string) $member->id,
            organizationId: $organization->id,
        );
    }

    public function removeMember(Organization $organization, User $member): void
    {
        if ($this->isLastOwner($organization, $member)) {
            throw ValidationException::withMessages([
                'member' => __('An organization must keep at least one Owner.'),
            ]);
        }

        DB::transaction(function () use ($organization, $member) {
            $organization->members()->detach($member->id);
            $organization->teams()->each(fn ($team) => $team->members()->detach($member->id));

            if ($member->current_organization_id === $organization->id) {
                $member->forceFill([
                    'current_organization_id' => $member->activeOrganizations()->value('organizations.id'),
                ])->save();
            }

            $this->audit->log(
                'member.removed',
                context: ['user_id' => $member->id],
                resourceType: 'user',
                resourceId: (string) $member->id,
                organizationId: $organization->id,
            );
        });
    }

    public function setMemberStatus(Organization $organization, User $member, string $status): void
    {
        if ($status === 'deactivated' && $this->isLastOwner($organization, $member)) {
            throw ValidationException::withMessages([
                'member' => __('An organization must keep at least one active Owner.'),
            ]);
        }

        $organization->members()->updateExistingPivot($member->id, ['status' => $status]);

        $this->audit->log(
            $status === 'deactivated' ? 'member.deactivated' : 'member.reactivated',
            context: ['user_id' => $member->id],
            resourceType: 'user',
            resourceId: (string) $member->id,
            organizationId: $organization->id,
        );
    }

    private function isLastOwner(Organization $organization, User $member): bool
    {
        return $member->roleIn($organization) === Role::Owner
            && $organization->owners()->count() === 1;
    }
}
