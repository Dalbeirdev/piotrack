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

        // CRM permission groupings.
        $crmAll = array_values(array_filter($all, fn (Permission $p) => str_starts_with($p->value, 'crm.')));
        $crmReadWrite = [
            Permission::CrmContactRead, Permission::CrmContactCreate, Permission::CrmContactUpdate,
            Permission::CrmCompanyRead, Permission::CrmCompanyCreate, Permission::CrmCompanyUpdate,
            Permission::CrmLeadRead, Permission::CrmLeadCreate, Permission::CrmLeadUpdate,
            Permission::CrmDealRead, Permission::CrmDealCreate, Permission::CrmDealUpdate,
            Permission::CrmActivityManage,
        ];
        $crmReadOnly = [
            Permission::CrmContactRead, Permission::CrmCompanyRead,
            Permission::CrmLeadRead, Permission::CrmDealRead,
        ];

        // Marketing permission groupings.
        $marketingAll = array_values(array_filter($all, fn (Permission $p) => str_starts_with($p->value, 'marketing.')));
        // Marketing users can build everything but not blast a send.
        $marketingBuild = [
            Permission::MarketingView,
            Permission::MarketingListsManage,
            Permission::MarketingFormsManage,
            Permission::MarketingCampaignsManage,
            Permission::MarketingAutomationManage,
            Permission::MarketingFunnelsView,
        ];

        // SEO permission grouping.
        $seoAll = array_values(array_filter($all, fn (Permission $p) => str_starts_with($p->value, 'seo.')));

        // Advertising permission grouping.
        $adsAll = array_values(array_filter($all, fn (Permission $p) => str_starts_with($p->value, 'ads.')));

        return [
            Role::Owner->value => $all,
            Role::Admin->value => $adminExceptDelete,
            Role::MarketingManager->value => [
                Permission::OrganizationView,
                Permission::MembersView,
                Permission::TeamsView,
                Permission::TeamsManage,
                Permission::AuditView,
                Permission::FilesView,
                Permission::FilesManage,
                Permission::IntegrationsView,
                Permission::IntegrationsManage,
                ...$crmAll,
                ...$marketingAll,
                ...$seoAll,
                ...$adsAll,
            ],
            Role::SalesManager->value => [
                Permission::OrganizationView,
                Permission::MembersView,
                Permission::TeamsView,
                Permission::TeamsManage,
                Permission::AuditView,
                Permission::FilesView,
                Permission::FilesManage,
                Permission::IntegrationsView,
                Permission::IntegrationsManage,
                ...$crmAll,
                Permission::MarketingView,
                Permission::MarketingFunnelsView,
                Permission::SeoView,
                Permission::AdsView,
            ],
            Role::MarketingUser->value => [
                Permission::OrganizationView,
                Permission::TeamsView,
                Permission::FilesView,
                Permission::FilesManage,
                Permission::IntegrationsView,
                ...$crmReadWrite,
                ...$marketingBuild,
                ...$seoAll,
                ...$adsAll,
            ],
            Role::SalesRepresentative->value => [
                Permission::OrganizationView,
                Permission::TeamsView,
                Permission::FilesView,
                Permission::IntegrationsView,
                ...$crmReadWrite,
                Permission::MarketingView,
                Permission::SeoView,
                Permission::AdsView,
            ],
            Role::Analyst->value => [
                Permission::OrganizationView,
                Permission::AuditView,
                Permission::FilesView,
                Permission::IntegrationsView,
                ...$crmReadOnly,
                Permission::MarketingView,
                Permission::MarketingFunnelsView,
                Permission::SeoView,
                Permission::SeoAuditsManage,
                Permission::AdsView,
            ],
            Role::BillingAdministrator->value => [
                Permission::OrganizationView,
                Permission::BillingView,
                Permission::BillingManage,
            ],
            Role::Viewer->value => [
                Permission::OrganizationView,
                Permission::FilesView,
                Permission::IntegrationsView,
                ...$crmReadOnly,
                Permission::MarketingView,
                Permission::SeoView,
                Permission::AdsView,
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
