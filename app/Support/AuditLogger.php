<?php

namespace App\Support;

use App\Models\AuditLog;

class AuditLogger
{
    /**
     * Record an audit event. Actor defaults to the authenticated user;
     * pass an explicit $actorId for events without one (e.g. failed logins).
     *
     * @param  array<string, mixed>  $context  Never include secrets or passwords.
     */
    public function log(
        string $action,
        array $context = [],
        ?int $actorId = null,
        ?string $resourceType = null,
        ?string $resourceId = null,
        ?int $tenantId = null,
    ): AuditLog {
        $request = request();

        return AuditLog::create([
            'tenant_id' => $tenantId,
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
