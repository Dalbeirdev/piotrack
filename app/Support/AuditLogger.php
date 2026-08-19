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
        return $this->write($action, $context, $actorId, $resourceType, $resourceId, $organizationId ?? $this->currentOrganization->id());
    }

    /**
     * Record a platform-level event that belongs to no tenant (feature-flag
     * changes, platform configuration).
     *
     * The organization is forced to null rather than defaulting to the current
     * one. A platform super admin who is also a member of an organization would
     * otherwise stamp a platform action with that tenant, and the tenant's own
     * organization-scoped audit viewer would surface it - showing a customer an
     * internal platform operation.
     *
     * @param  array<string, mixed>  $context  Never include secrets or passwords.
     */
    public function platform(
        string $action,
        array $context = [],
        ?int $actorId = null,
        ?string $resourceType = null,
        ?string $resourceId = null,
    ): AuditLog {
        return $this->write($action, $context, $actorId, $resourceType, $resourceId, null);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function write(
        string $action,
        array $context,
        ?int $actorId,
        ?string $resourceType,
        ?string $resourceId,
        ?int $organizationId,
    ): AuditLog {
        $request = request();

        return AuditLog::create([
            'organization_id' => $organizationId,
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
