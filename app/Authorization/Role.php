<?php

namespace App\Authorization;

/**
 * Roles come in two scopes (Master Prompt §5):
 *  - Organization roles are stored on the organization_user membership and
 *    resolved against the user's *current* organization.
 *  - Platform roles are global staff roles stored on users.platform_role;
 *    only Super Admin's global bypass is exercised before the platform admin
 *    area (Stage 13).
 */
enum Role: string
{
    // Organization roles
    case Owner = 'owner';
    case Admin = 'admin';
    case MarketingManager = 'marketing_manager';
    case SalesManager = 'sales_manager';
    case SalesRepresentative = 'sales_representative';
    case MarketingUser = 'marketing_user';
    case Analyst = 'analyst';
    case BillingAdministrator = 'billing_administrator';
    case Viewer = 'viewer';
    case Client = 'client';

    // Platform roles
    case PlatformSuperAdmin = 'platform_super_admin';
    case PlatformAdmin = 'platform_admin';
    case PlatformSupportAdmin = 'platform_support_admin';
    case PlatformFinanceAdmin = 'platform_finance_admin';
    case PlatformReadOnlySupport = 'platform_read_only_support';

    public function isPlatform(): bool
    {
        return str_starts_with($this->value, 'platform_');
    }

    public function isOrganizationRole(): bool
    {
        return ! $this->isPlatform();
    }

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Admin => 'Administrator',
            self::MarketingManager => 'Marketing Manager',
            self::SalesManager => 'Sales Manager',
            self::SalesRepresentative => 'Sales Representative',
            self::MarketingUser => 'Marketing User',
            self::Analyst => 'Analyst',
            self::BillingAdministrator => 'Billing Administrator',
            self::Viewer => 'Viewer',
            self::Client => 'Client',
            self::PlatformSuperAdmin => 'Super Administrator',
            self::PlatformAdmin => 'Platform Administrator',
            self::PlatformSupportAdmin => 'Support Administrator',
            self::PlatformFinanceAdmin => 'Finance Administrator',
            self::PlatformReadOnlySupport => 'Read-only Support',
        };
    }

    /**
     * Organization roles that can be assigned to members (all org roles,
     * including Owner — an organization may have more than one; the
     * last-Owner invariant is enforced by OrganizationService). Platform
     * roles are excluded.
     *
     * @return list<self>
     */
    public static function assignableOrganizationRoles(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $r) => $r->isOrganizationRole(),
        ));
    }
}
