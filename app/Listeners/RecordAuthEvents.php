<?php

namespace App\Listeners;

use App\Support\AuditLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Records authentication lifecycle events into the audit trail (AUTH-008).
 * Context never contains passwords or secrets.
 *
 * Wired by Laravel listener auto-discovery: each handle* method is bound to
 * its type-hinted event. Never ALSO subscribe this class manually — doing
 * both records every event twice (found and fixed during Stage 1 QA).
 */
class RecordAuthEvents
{
    public function __construct(private AuditLogger $audit) {}

    public function handleLogin(Login $event): void
    {
        $this->audit->log('auth.login', actorId: (int) $event->user->getAuthIdentifier());
    }

    public function handleLogout(Logout $event): void
    {
        if ($event->user !== null) {
            $this->audit->log('auth.logout', actorId: (int) $event->user->getAuthIdentifier());
        }
    }

    public function handleFailed(Failed $event): void
    {
        $this->audit->log(
            'auth.login_failed',
            context: ['email' => $event->credentials['email'] ?? null],
            actorId: $event->user ? (int) $event->user->getAuthIdentifier() : null,
        );
    }

    public function handleLockout(Lockout $event): void
    {
        $this->audit->log('auth.lockout', context: [
            'email' => (string) $event->request->input('email'),
        ]);
    }

    public function handleRegistered(Registered $event): void
    {
        $this->audit->log('auth.registered', actorId: (int) $event->user->getAuthIdentifier());
    }

    public function handleVerified(Verified $event): void
    {
        if ($event->user instanceof Authenticatable) {
            $this->audit->log('auth.email_verified', actorId: (int) $event->user->getAuthIdentifier());
        }
    }

    public function handlePasswordReset(PasswordReset $event): void
    {
        $this->audit->log('auth.password_reset', actorId: (int) $event->user->getAuthIdentifier());
    }
}
