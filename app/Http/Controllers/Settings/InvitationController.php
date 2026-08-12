<?php

namespace App\Http\Controllers\Settings;

use App\Authorization\Role;
use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Notifications\OrganizationInvitation;
use App\Services\OrganizationService;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

class InvitationController extends Controller
{
    public function __construct(
        private OrganizationService $organizations,
        private CurrentOrganization $currentOrganization,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $organization = $this->currentOrganization->get();

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::in(array_map(fn (Role $r) => $r->value, Role::assignableOrganizationRoles()))],
        ]);

        ['token' => $token] = $this->organizations->invite(
            $organization,
            $request->user(),
            $validated['email'],
            Role::from($validated['role']),
        );

        Notification::route('mail', $validated['email'])
            ->notify(new OrganizationInvitation($organization->name, $token));

        return back();
    }

    public function resend(Invitation $invitation): RedirectResponse
    {
        // {invitation} is tenant-scoped via BelongsToTenant, so this is already
        // guaranteed to belong to the current organization.
        ['token' => $token] = $this->organizations->resendInvitation($invitation);

        Notification::route('mail', $invitation->email)
            ->notify(new OrganizationInvitation(
                (string) $invitation->organization()->value('name'),
                $token,
            ));

        return back();
    }

    public function destroy(Invitation $invitation): RedirectResponse
    {
        $this->organizations->revokeInvitation($invitation);

        return back();
    }
}
