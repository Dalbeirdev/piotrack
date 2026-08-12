<?php

namespace App\Http\Controllers\Settings;

use App\Authorization\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OrganizationService;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MemberController extends Controller
{
    public function __construct(
        private OrganizationService $organizations,
        private CurrentOrganization $currentOrganization,
    ) {}

    public function index(): Response
    {
        $organization = $this->currentOrganization->get();

        $members = $organization->members()
            ->orderBy('name')
            ->get(['users.id', 'users.name', 'users.email'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->getAttribute('pivot')?->role,
                'status' => $user->getAttribute('pivot')?->status,
            ]);

        $invitations = $organization->invitations()
            ->pending()
            ->latest()
            ->get(['id', 'email', 'role', 'expires_at'])
            ->map(fn ($invitation) => [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'role' => $invitation->role,
                'expires_at' => $invitation->expires_at,
            ]);

        return Inertia::render('settings/members', [
            'members' => $members,
            'invitations' => $invitations,
            'assignableRoles' => $this->assignableRoles(),
        ]);
    }

    public function updateRole(Request $request, User $member): RedirectResponse
    {
        $organization = $this->currentOrganization->get();
        abort_unless($member->isMemberOf($organization), 404);

        $validated = $request->validate([
            'role' => ['required', Rule::in(array_map(fn (Role $r) => $r->value, Role::assignableOrganizationRoles()))],
        ]);

        $this->organizations->changeMemberRole($organization, $member, Role::from($validated['role']));

        return back();
    }

    public function destroy(User $member): RedirectResponse
    {
        $organization = $this->currentOrganization->get();
        abort_unless($member->isMemberOf($organization), 404);

        $this->organizations->removeMember($organization, $member);

        return back();
    }

    public function deactivate(User $member): RedirectResponse
    {
        $organization = $this->currentOrganization->get();
        abort_unless($member->isMemberOf($organization), 404);

        $this->organizations->setMemberStatus($organization, $member, 'deactivated');

        return back();
    }

    public function reactivate(User $member): RedirectResponse
    {
        $organization = $this->currentOrganization->get();
        abort_unless($member->isMemberOf($organization), 404);

        $this->organizations->setMemberStatus($organization, $member, 'active');

        return back();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function assignableRoles(): array
    {
        return array_map(
            fn (Role $role) => ['value' => $role->value, 'label' => $role->label()],
            Role::assignableOrganizationRoles(),
        );
    }
}
