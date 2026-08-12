<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Services\OrganizationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Invitation acceptance. Reached from the emailed link. Requires the invitee to
 * be authenticated with the invited email address; the token is looked up
 * across tenants (the accepting user is not yet a member of the target org).
 */
class InvitationAcceptanceController extends Controller
{
    public function __construct(private OrganizationService $organizations) {}

    public function show(Request $request, string $token): Response|RedirectResponse
    {
        $invitation = $this->resolve($token);

        $organizationName = $invitation?->organization()->value('name');
        $user = $request->user();

        return Inertia::render('invitations/accept', [
            'token' => $token,
            'valid' => $invitation !== null,
            'organizationName' => $organizationName,
            'email' => $invitation?->email,
            'emailMatches' => $invitation !== null && $user !== null
                && Str::lower($user->email) === Str::lower($invitation->email),
            'authenticated' => $user !== null,
        ]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = $this->resolve($token);

        if ($invitation === null) {
            return redirect()->route('dashboard')->with('error', __('This invitation is no longer valid.'));
        }

        $user = $request->user();

        abort_unless(
            Str::lower($user->email) === Str::lower($invitation->email),
            403,
            __('This invitation was sent to a different email address.'),
        );

        $this->organizations->acceptInvitation($invitation, $user);

        return redirect()->route('dashboard');
    }

    /**
     * Resolve a pending, unexpired invitation by its plaintext token. Bypasses
     * the tenant scope because the accepting user is not yet in the target org.
     */
    private function resolve(string $token): ?Invitation
    {
        return Invitation::withoutTenantScope()
            ->where('token', hash('sha256', $token))
            ->pending()
            ->first();
    }
}
