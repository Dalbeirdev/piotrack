<?php

namespace App\Support;

use App\Models\Organization;

/**
 * Request-scoped holder for the tenant the current user is acting within.
 * Bound as a singleton; set by the SetCurrentOrganization middleware and read
 * by the BelongsToTenant global scope, the RBAC gate, and the audit logger.
 */
class CurrentOrganization
{
    private ?Organization $organization = null;

    public function set(?Organization $organization): void
    {
        $this->organization = $organization;
    }

    public function get(): ?Organization
    {
        return $this->organization;
    }

    public function id(): ?int
    {
        return $this->organization?->id;
    }

    public function isSet(): bool
    {
        return $this->organization !== null;
    }

    public function forget(): void
    {
        $this->organization = null;
    }
}
