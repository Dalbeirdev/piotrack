<?php

namespace App\Authorization;

/**
 * Maps each organization role to the permissions it grants within an
 * organization. Owner is intentionally granted every permission and also
 * receives a Gate bypass within its own organization. Platform roles hold no
 * org-scoped permissions (Super Admin bypasses globally); their platform
 * capabilities arrive with the platform admin area (Stage 13).
 *
 * This map grows as modules add permissions to {@see Permission}.
 */
class RolePermissions
{
    /**
     * @return array<string, list<Permission>>
     */
    public static function map(): array
    {
        $all = Permission::cases();

        $adminExceptDelete = array_values(array_filter(
            $all,
            fn (Permission $p) => $p !== Permission::OrganizationDelete,
        ));

        return [
            Role::Owner->value => $all,
            Role::Admin->value => $adminExceptDelete,
            Role::MarketingManager->value => [
                Permission::OrganizationView,
                Permission::MembersView,
                Permission::TeamsView,
                Permission::TeamsManage,
                Permission::AuditView,
            ],
            Role::SalesManager->value => [
                Permission::OrganizationView,
                Permission::MembersView,
                Permission::TeamsView,
                Permission::TeamsManage,
                Permission::AuditView,
            ],
            Role::MarketingUser->value => [
                Permission::OrganizationView,
                Permission::TeamsView,
            ],
            Role::SalesRepresentative->value => [
                Permission::OrganizationView,
                Permission::TeamsView,
            ],
            Role::Analyst->value => [
                Permission::OrganizationView,
                Permission::AuditView,
            ],
            Role::BillingAdministrator->value => [
                Permission::OrganizationView,
            ],
            Role::Viewer->value => [
                Permission::OrganizationView,
            ],
        ];
    }

    /**
     * The permissions a role grants, as permission-key strings.
     *
     * @return list<string>
     */
    public static function for(string $role): array
    {
        $permissions = self::map()[$role] ?? [];

        return array_map(fn (Permission $p) => $p->value, $permissions);
    }

    public static function grants(string $role, Permission $permission): bool
    {
        return in_array($permission, self::map()[$role] ?? [], true);
    }
}
