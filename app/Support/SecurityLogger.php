<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

/**
 * Security event logging and alerting (SEC-008).
 *
 * Security events go to the audit trail AND to a dedicated log channel, so they
 * survive independently of the tenant database and can be shipped to an alerting
 * pipeline. Events above the alert threshold are logged at `critical` so an
 * external monitor can page on severity alone.
 */
class SecurityLogger
{
    /** Events serious enough to page on. */
    private const ALERTABLE = [
        'security.impersonation.refused',
        'security.privilege_escalation_attempt',
        'security.ssrf_blocked',
        'security.webhook_signature_invalid',
        'security.account_deleted',
    ];

    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function record(string $event, array $context = []): void
    {
        $request = request();

        $entry = $context + [
            'event' => $event,
            'ip' => $request->ip(),
            'user_id' => $request->user()?->getAuthIdentifier(),
            'request_id' => $request->attributes->get('request_id'),
            'path' => $request->path(),
        ];

        in_array($event, self::ALERTABLE, true)
            ? Log::critical('security event', $entry)
            : Log::warning('security event', $entry);

        // Best-effort mirror into the tenant audit trail; a security event must
        // never be lost because there is no current organization.
        try {
            $this->audit->log($event, context: $context);
        } catch (\Throwable) {
            // The log channel above is the durable record.
        }
    }
}
