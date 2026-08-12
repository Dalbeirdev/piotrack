import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarMenu, SidebarMenuButton, SidebarMenuItem, useSidebar } from '@/components/ui/sidebar';
import { useIsMobile } from '@/hooks/use-mobile';
import { type SharedData } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { Building2, Check, ChevronsUpDown, Plus } from 'lucide-react';

export function OrganizationSwitcher() {
    const { auth } = usePage<SharedData>().props;
    const { state } = useSidebar();
    const isMobile = useIsMobile();

    const current = auth.currentOrganization;
    const organizations = auth.organizations ?? [];

    if (!current) {
        return null;
    }

    const switchTo = (id: number) => {
        if (id !== current.id) {
            router.post(`/organizations/${id}/switch`, {}, { preserveScroll: true });
        }
    };

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <SidebarMenuButton size="lg" className="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground">
                            <div className="bg-sidebar-primary text-sidebar-primary-foreground flex aspect-square size-8 items-center justify-center rounded-lg">
                                <Building2 className="size-4" />
                            </div>
                            <div className="grid flex-1 text-left text-sm leading-tight">
                                <span className="truncate font-semibold">{current.name}</span>
                                <span className="text-muted-foreground truncate text-xs">Organization</span>
                            </div>
                            <ChevronsUpDown className="ml-auto size-4" />
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        className="w-(--radix-dropdown-menu-trigger-width) min-w-56 rounded-lg"
                        align="start"
                        side={isMobile ? 'bottom' : state === 'collapsed' ? 'right' : 'bottom'}
                    >
                        <DropdownMenuLabel className="text-muted-foreground text-xs">Organizations</DropdownMenuLabel>
                        {organizations.map((org) => (
                            <DropdownMenuItem key={org.id} onClick={() => switchTo(org.id)} className="gap-2">
                                <Building2 className="size-4" />
                                <span className="flex-1 truncate">{org.name}</span>
                                {org.id === current.id && <Check className="size-4" />}
                            </DropdownMenuItem>
                        ))}
                        <DropdownMenuSeparator />
                        <DropdownMenuItem asChild>
                            <Link href="/organizations/create" className="gap-2">
                                <Plus className="size-4" />
                                <span>Create organization</span>
                            </Link>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
