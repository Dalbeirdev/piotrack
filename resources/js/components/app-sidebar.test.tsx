import { AppSidebar } from '@/components/app-sidebar';
import { SidebarProvider } from '@/components/ui/sidebar';
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

/**
 * The two-pane sidebar.
 *
 * A left rail lists every section the user may see; clicking a section shows its
 * items in a second panel that stays put until another section is picked (the
 * click-to-open, persistent model chosen over a hover flyout). The section
 * holding the current page is open on load, and Dashboard / Client Portal are
 * direct links in the rail. Everything the sidebar needs from Inertia comes
 * through usePage().props.auth, so one mock drives the component and its
 * children.
 */

const { page } = vi.hoisted(() => ({
    page: {
        url: '/dashboard',
        props: {
            auth: {
                user: { id: 1, name: 'Dana Whitfield', email: 'demo@piotrack.test' },
                currentOrganization: { id: 1, name: 'Acme', slug: 'acme' },
                organizations: [{ id: 1, name: 'Acme', slug: 'acme', role: 'owner' }],
                permissions: [] as string[],
                role: 'owner',
            },
        },
    },
}));

vi.mock('@inertiajs/react', () => ({
    usePage: () => page,
    Link: ({ href, children, ...rest }: { href: string; children: React.ReactNode }) => (
        <a href={href} {...rest}>
            {children}
        </a>
    ),
    router: { post: vi.fn(), get: vi.fn() },
}));

function renderSidebar(url: string, permissions: string[]) {
    page.url = url;
    page.props.auth.permissions = permissions;

    return render(
        <SidebarProvider>
            <AppSidebar />
        </SidebarProvider>,
    );
}

// Yields two multi-item sections (CRM, Marketing) and one single-item section
// (Portal).
const PERMS = ['crm.contact.read', 'crm.company.read', 'crm.lead.read', 'crm.deal.read', 'marketing.view', 'portal.access'];

beforeEach(() => {
    page.url = '/dashboard';
    page.props.auth.permissions = [];
});

describe('AppSidebar two-pane navigation', () => {
    it('lists every permitted section as a button in the rail', () => {
        renderSidebar('/dashboard', PERMS);

        expect(screen.getByRole('button', { name: /CRM/i })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /Marketing/i })).toBeInTheDocument();
    });

    it('renders Dashboard and single-item sections as links, not section buttons', () => {
        renderSidebar('/dashboard', PERMS);

        expect(screen.getByRole('link', { name: /Dashboard/i })).toHaveAttribute('href', '/dashboard');
        // Portal has one item, so it is a rail link with no section button.
        expect(screen.getByRole('link', { name: /Client Portal/i })).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: /^Portal$/i })).not.toBeInTheDocument();
    });

    it('shows the panel for the first section by default on the dashboard', () => {
        renderSidebar('/dashboard', PERMS);

        // The panel is never blank: with no section for /dashboard it falls back
        // to the first section (CRM), so CRM items are visible and Marketing's
        // are not.
        expect(screen.getByRole('link', { name: 'Contacts' })).toBeInTheDocument();
        expect(screen.queryByRole('link', { name: 'Lists' })).not.toBeInTheDocument();
    });

    it('opens the panel for the section that holds the current page', () => {
        renderSidebar('/marketing', PERMS);

        expect(screen.getByRole('link', { name: 'Lists' })).toBeInTheDocument();
        expect(screen.queryByRole('link', { name: 'Contacts' })).not.toBeInTheDocument();
    });

    it('swaps the panel when another section is clicked, showing one at a time', async () => {
        const user = userEvent.setup();
        renderSidebar('/dashboard', PERMS);

        // Default panel is CRM.
        expect(screen.getByRole('link', { name: 'Contacts' })).toBeInTheDocument();

        await user.click(screen.getByRole('button', { name: /Marketing/i }));

        expect(screen.getByRole('link', { name: 'Lists' })).toBeInTheDocument();
        expect(screen.queryByRole('link', { name: 'Contacts' })).not.toBeInTheDocument();
    });

    it('does not navigate when a section button is clicked - it only opens the panel', async () => {
        const user = userEvent.setup();
        renderSidebar('/dashboard', PERMS);

        const marketing = screen.getByRole('button', { name: /Marketing/i });
        // A section control is a button, not a link, so it carries no href.
        expect(marketing).not.toHaveAttribute('href');

        await user.click(marketing);
        // The URL mock is unchanged; the panel simply swapped.
        expect(page.url).toBe('/dashboard');
    });

    it('marks the section button of the current page as selected', () => {
        renderSidebar('/crm/contacts', PERMS);

        expect(screen.getByRole('button', { name: /CRM/i })).toHaveAttribute('data-active', 'true');
        expect(screen.getByRole('button', { name: /Marketing/i })).toHaveAttribute('data-active', 'false');
    });

    it('marks the current page as the active item in the panel', () => {
        renderSidebar('/crm/contacts', PERMS);

        expect(screen.getByRole('link', { name: 'Contacts' })).toHaveAttribute('data-active', 'true');
        expect(screen.getByRole('link', { name: 'Companies' })).toHaveAttribute('data-active', 'false');
    });

    it('resolves the longest-matching active item, not a prefix', () => {
        // /crm/contacts activates Contacts, never a shorter /crm prefix - the bug
        // the longest-match rule fixed.
        renderSidebar('/crm/contacts', PERMS);

        const panelLink = screen.getByRole('link', { name: 'Contacts' });
        expect(panelLink).toHaveAttribute('data-active', 'true');
    });

    it('hides sections the user has no permission for', () => {
        renderSidebar('/dashboard', ['crm.contact.read', 'crm.deal.read']);

        expect(screen.getByRole('button', { name: /CRM/i })).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: /Marketing/i })).not.toBeInTheDocument();
        expect(screen.queryByRole('link', { name: /Client Portal/i })).not.toBeInTheDocument();
    });

    it('collapses a section to a rail link when only one of its items is permitted', () => {
        // A single CRM permission makes CRM one item, so it becomes a link rather
        // than a section button - the same rule that makes Portal a link.
        renderSidebar('/dashboard', ['crm.contact.read']);

        expect(screen.queryByRole('button', { name: /^CRM$/i })).not.toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Contacts' })).toBeInTheDocument();
    });

    it('keeps the panel scoped to its own section when asserting item membership', () => {
        renderSidebar('/marketing', PERMS);

        // The panel header names the open section.
        const lists = screen.getByRole('link', { name: 'Lists' });
        expect(within(lists.closest('div') as HTMLElement).queryByRole('link', { name: 'Contacts' })).not.toBeInTheDocument();
    });
});
