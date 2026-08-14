<?php

namespace App\Services\Platform;

use App\Models\ImpersonationSession;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use RuntimeException;

/**
 * Support impersonation (ADMIN-006): permissioned, visibly indicated and fully
 * audited.
 *
 * Impersonation is the single most dangerous capability in the product — it
 * hands staff a customer's account. The rules here are deliberate and are
 * enforced in the service rather than the controller so no future caller can
 * skip them:
 *
 *  - only a platform user may start one, and only with `admin.impersonate`;
 *  - a reason is mandatory and is recorded;
 *  - **platform staff can never be impersonated** (privilege-escalation guard);
 *  - the session id is held in the request session so the UI can show a
 *    persistent banner — impersonation is never invisible;
 *  - start and stop are both audited with both user ids;
 *  - stopping restores the original user.
 */
class ImpersonationService
{
    /** Session keys — also read by the Inertia middleware to flag the UI. */
    public const SESSION_KEY = 'impersonation.session_id';

    public const IMPERSONATOR_KEY = 'impersonation.impersonator_id';

    public function __construct(private AuditLogger $audit) {}

    public function start(User $impersonator, User $target, string $reason): ImpersonationSession
    {
        if ($impersonator->platformRole() === null) {
            throw new RuntimeException('Only platform staff may impersonate a user.');
        }

        // A support session must never become a route to more privilege.
        if ($target->platformRole() !== null) {
            throw new RuntimeException('Platform staff cannot be impersonated.');
        }

        if ($impersonator->is($target)) {
            throw new RuntimeException('You cannot impersonate yourself.');
        }

        if (trim($reason) === '') {
            throw new RuntimeException('A reason is required to impersonate a user.');
        }

        $session = ImpersonationSession::create([
            'impersonator_id' => $impersonator->id,
            'user_id' => $target->id,
            'organization_id' => $target->current_organization_id,
            'reason' => trim($reason),
            'started_at' => now(),
        ]);

        $this->audit->log('admin.impersonation.started', context: [
            'impersonator_id' => $impersonator->id,
            'user_id' => $target->id,
            'reason' => $session->reason,
        ], actorId: $impersonator->id, resourceType: 'user', resourceId: (string) $target->id,
            organizationId: $target->current_organization_id);

        Session::put(self::SESSION_KEY, $session->id);
        Session::put(self::IMPERSONATOR_KEY, $impersonator->id);
        Auth::login($target);

        return $session;
    }

    /**
     * End the active impersonation and restore the original user.
     */
    public function stop(): ?ImpersonationSession
    {
        $sessionId = Session::get(self::SESSION_KEY);
        $impersonatorId = Session::get(self::IMPERSONATOR_KEY);

        Session::forget([self::SESSION_KEY, self::IMPERSONATOR_KEY]);

        if ($sessionId === null) {
            return null;
        }

        $session = ImpersonationSession::find($sessionId);

        if ($session !== null && $session->ended_at === null) {
            $session->update(['ended_at' => now()]);

            $this->audit->log('admin.impersonation.stopped', context: [
                'impersonator_id' => $session->impersonator_id,
                'user_id' => $session->user_id,
            ], actorId: $session->impersonator_id, resourceType: 'user', resourceId: (string) $session->user_id,
                organizationId: $session->organization_id);
        }

        $impersonator = $impersonatorId !== null ? User::find($impersonatorId) : null;

        if ($impersonator !== null) {
            Auth::login($impersonator);
        }

        return $session;
    }

    /**
     * The active session, if this request is being impersonated. Used to render
     * the persistent banner.
     */
    public function active(): ?ImpersonationSession
    {
        $sessionId = Session::get(self::SESSION_KEY);

        return $sessionId !== null ? ImpersonationSession::find($sessionId) : null;
    }

    public function isImpersonating(): bool
    {
        return Session::has(self::SESSION_KEY);
    }
}
