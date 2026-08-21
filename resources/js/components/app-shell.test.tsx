import { AppShell } from '@/components/app-shell';
import { Sidebar, SidebarContent } from '@/components/ui/sidebar';
import { render } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

/**
 * The dashboard opens with the sidebar collapsed to its icon rail so the command
 * centre reads full-width; every other page uses the saved preference, and the
 * dashboard's auto-collapse never overwrites that preference.
 */

const { page } = vi.hoisted(() => ({ page: { url: '/dashboard' } }));

vi.mock('@inertiajs/react', () => ({ usePage: () => page }));

function renderShell() {
    return render(
        <AppShell variant="sidebar">
            <Sidebar collapsible="icon">
                <SidebarContent>nav</SidebarContent>
            </Sidebar>
        </AppShell>,
    );
}

/** The sidebar's collapsible state is exposed on the wrapper's data-state. */
function sidebarState(container: HTMLElement): string | null {
    return container.querySelector('[data-state]')?.getAttribute('data-state') ?? null;
}

beforeEach(() => {
    localStorage.clear();
    page.url = '/dashboard';
});

describe('AppShell sidebar default', () => {
    it('collapses the sidebar on the dashboard', () => {
        page.url = '/dashboard';
        const { container } = renderShell();
        expect(sidebarState(container)).toBe('collapsed');
    });

    it('keeps the sidebar expanded on other pages', () => {
        page.url = '/crm/contacts';
        const { container } = renderShell();
        expect(sidebarState(container)).toBe('expanded');
    });

    it('does not overwrite the saved preference from the dashboard', () => {
        // A user who has never collapsed the sidebar keeps the default preference
        // even after visiting the dashboard, which collapses only visually.
        page.url = '/dashboard';
        renderShell();
        expect(localStorage.getItem('sidebar')).toBeNull();
    });

    it('honours a saved collapsed preference on other pages', () => {
        localStorage.setItem('sidebar', 'false');
        page.url = '/crm/contacts';
        const { container } = renderShell();
        expect(sidebarState(container)).toBe('collapsed');
    });
});
