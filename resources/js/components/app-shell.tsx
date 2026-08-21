import { SidebarProvider } from '@/components/ui/sidebar';
import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

interface AppShellProps {
    children: React.ReactNode;
    variant?: 'header' | 'sidebar';
}

/** The user's saved sidebar preference (open unless they collapsed it). */
function savedPreference(): boolean {
    return typeof window !== 'undefined' ? localStorage.getItem('sidebar') !== 'false' : true;
}

export function AppShell({ children, variant = 'header' }: AppShellProps) {
    // The dashboard is a command centre - it reads better full-width, so the
    // sidebar starts collapsed to its icon rail there. Every other page uses the
    // saved preference. The toggle still works on the dashboard; it just is not
    // open by default.
    const path = usePage().url.split('?')[0];
    const isDashboard = path === '/dashboard';

    const [isOpen, setIsOpen] = useState<boolean>(() => (isDashboard ? false : savedPreference()));

    // Re-apply the rule whenever we move onto or off the dashboard.
    useEffect(() => {
        setIsOpen(isDashboard ? false : savedPreference());
    }, [isDashboard]);

    const handleSidebarChange = (open: boolean) => {
        setIsOpen(open);

        // Persist the choice only away from the dashboard, so the dashboard's
        // auto-collapse never overwrites the user's normal preference.
        if (!isDashboard && typeof window !== 'undefined') {
            localStorage.setItem('sidebar', String(open));
        }
    };

    if (variant === 'header') {
        return <div className="flex min-h-screen w-full flex-col">{children}</div>;
    }

    return (
        <SidebarProvider defaultOpen={isOpen} open={isOpen} onOpenChange={handleSidebarChange}>
            {children}
        </SidebarProvider>
    );
}
