<?php

namespace App\Authorization;

/**
 * The permission registry — the single source of truth for authorization
 * (ADR-0002). Naming is `resource.action`. This list grows per module; each
 * new module adds its own permissions here and maps them onto roles in
 * {@see RolePermissions}. Enforcement is via Laravel's Gate; the frontend
 * receives the resolved list for UX gating only, never as a security boundary.
 */
enum Permission: string
{
    // Organization
    case OrganizationView = 'organization.view';
    case OrganizationUpdate = 'organization.update';
    case OrganizationDelete = 'organization.delete';

    // Members / memberships
    case MembersView = 'members.view';
    case MembersInvite = 'members.invite';
    case MembersUpdate = 'members.update';
    case MembersRemove = 'members.remove';

    // Teams
    case TeamsView = 'teams.view';
    case TeamsManage = 'teams.manage';

    // Roles & audit
    case RolesView = 'roles.view';
    case AuditView = 'audit.view';

    // Billing
    case BillingView = 'billing.view';
    case BillingManage = 'billing.manage';

    // Settings
    case SettingsManage = 'settings.manage';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $p) => $p->value, self::cases());
    }
}
