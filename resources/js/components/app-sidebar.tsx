import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { OrganizationSwitcher } from '@/components/organization-switcher';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader } from '@/components/ui/sidebar';
import { usePermissions } from '@/hooks/use-permissions';
import { type NavItem } from '@/types';
import {
    Bell,
    BookOpen,
    Building2,
    CalendarClock,
    Code,
    FileSearch,
    FileText,
    Filter,
    Flame,
    Gauge,
    Handshake,
    KeyRound,
    LayoutGrid,
    LayoutTemplate,
    List,
    Mail,
    MapPin,
    Megaphone,
    PenLine,
    Radar,
    Search,
    Send,
    Share2,
    Sparkles,
    Star,
    Target,
    UserPlus,
    Users,
    Workflow,
} from 'lucide-react';

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

    const seoNavItems: NavItem[] = [
        can('seo.view') && { title: 'Dashboard', url: '/seo', icon: Search },
        can('seo.view') && { title: 'Audit', url: '/seo/audits', icon: FileSearch },
        can('seo.view') && { title: 'Keywords', url: '/seo/keywords', icon: KeyRound },
        can('seo.view') && { title: 'Local', url: '/seo/local', icon: MapPin },
        can('seo.view') && { title: 'AI Visibility', url: '/seo/ai-visibility', icon: Sparkles },
        can('seo.view') && { title: 'Schema', url: '/seo/schema', icon: Code },
    ].filter(Boolean) as NavItem[];

    const adsNavItems: NavItem[] = [
        can('ads.view') && { title: 'Dashboard', url: '/ads', icon: Megaphone },
        can('ads.view') && { title: 'Campaigns', url: '/ads/campaigns', icon: Target },
        can('ads.view') && { title: 'Retargeting', url: '/ads/retargeting', icon: Users },
    ].filter(Boolean) as NavItem[];

    const contentNavItems: NavItem[] = [
        can('content.view') && { title: 'Dashboard', url: '/content', icon: PenLine },
        can('content.view') && { title: 'Content', url: '/content/pieces', icon: FileText },
        can('content.view') && { title: 'Social', url: '/content/social', icon: Share2 },
        can('content.view') && { title: 'Reputation', url: '/content/reputation', icon: Star },
        can('content.view') && { title: 'Outreach', url: '/content/outreach', icon: Send },
    ].filter(Boolean) as NavItem[];

    const salesNavItems: NavItem[] = [
        can('sales.view') && { title: 'Dashboard', url: '/sales', icon: Gauge },
        can('sales.view') && { title: 'Scoring', url: '/sales/scoring', icon: Flame },
        can('sales.view') && { title: 'Intent', url: '/sales/intent', icon: Radar },
        can('sales.view') && { title: 'Alerts', url: '/sales/alerts', icon: Bell },
        can('sales.view') && { title: 'Booking', url: '/sales/booking', icon: CalendarClock },
        can('sales.view') && { title: 'Enablement', url: '/sales/enablement', icon: BookOpen },
        can('sales.view') && { title: 'Accounts', url: '/sales/accounts', icon: Building2 },
    ].filter(Boolean) as NavItem[];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <OrganizationSwitcher />
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
                {crmNavItems.length > 0 && <NavMain items={crmNavItems} label="CRM" />}
                {marketingNavItems.length > 0 && <NavMain items={marketingNavItems} label="Marketing" />}
                {seoNavItems.length > 0 && <NavMain items={seoNavItems} label="SEO" />}
                {adsNavItems.length > 0 && <NavMain items={adsNavItems} label="Advertising" />}
                {contentNavItems.length > 0 && <NavMain items={contentNavItems} label="Content" />}
                {salesNavItems.length > 0 && <NavMain items={salesNavItems} label="Sales" />}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
