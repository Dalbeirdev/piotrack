import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { OrganizationSwitcher } from '@/components/organization-switcher';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader } from '@/components/ui/sidebar';
import { usePermissions } from '@/hooks/use-permissions';
import { type NavItem } from '@/types';
import { Building2, FileText, Filter, Handshake, LayoutGrid, LayoutTemplate, List, Mail, Megaphone, UserPlus, Users, Workflow } from 'lucide-react';

export function AppSidebar() {
    const { can } = usePermissions();

    const mainNavItems: NavItem[] = [{ title: 'Dashboard', url: '/dashboard', icon: LayoutGrid }];

    const crmNavItems: NavItem[] = [
        can('crm.contact.read') && { title: 'Contacts', url: '/crm/contacts', icon: Users },
        can('crm.company.read') && { title: 'Companies', url: '/crm/companies', icon: Building2 },
        can('crm.lead.read') && { title: 'Leads', url: '/crm/leads', icon: UserPlus },
        can('crm.deal.read') && { title: 'Deals', url: '/crm/deals', icon: Handshake },
    ].filter(Boolean) as NavItem[];

    const marketingNavItems: NavItem[] = can('marketing.view')
        ? [
              { title: 'Marketing', url: '/marketing', icon: Megaphone },
              { title: 'Lists', url: '/marketing/lists', icon: List },
              { title: 'Forms', url: '/marketing/forms', icon: FileText },
              { title: 'Landing Pages', url: '/marketing/landing-pages', icon: LayoutTemplate },
              { title: 'Campaigns', url: '/marketing/campaigns', icon: Mail },
              { title: 'Automation', url: '/marketing/automation', icon: Workflow },
              { title: 'Funnels', url: '/marketing/funnels', icon: Filter },
          ]
        : [];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <OrganizationSwitcher />
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
                {crmNavItems.length > 0 && <NavMain items={crmNavItems} label="CRM" />}
                {marketingNavItems.length > 0 && <NavMain items={marketingNavItems} label="Marketing" />}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
