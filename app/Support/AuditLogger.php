<?php

namespace App\Support;

use App\Models\AuditLog;

class AuditLogger
{
    public function __construct(private CurrentOrganization $currentOrganization) {}

    /**
     * Record an audit event. Actor defaults to the authenticated user and
     * organization to the current tenant; pass explicit values for events that
     * have neither (e.g. failed logins) or that target a different tenant.
     *
     * @param  array<string, mixed>  $context  Never include secrets or passwords.
     */
    public function log(
        string $action,
        array $context = [],
        ?int $actorId = null,
        ?string $resourceType = null,
        ?string $resourceId = null,
        ?int $organizationId = null,
    ): AuditLog {
        $request = request();

        return AuditLog::create([
            'organization_id' => $organizationId ?? $this->currentOrganization->id(),
            'actor_id' => $actorId ?? $request->user()?->getAuthIdentifier(),
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'context' => $context === [] ? null : $context,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 512) ?: null,
            'created_at' => now(),
        ]);
    }
}
