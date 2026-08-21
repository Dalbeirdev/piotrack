import { AppSidebar } from '@/components/app-sidebar';
import { SidebarProvider } from '@/components/ui/sidebar';
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

/**
 * The sidebar accordion, finally under a real test.
 *
 * This is the behaviour that shipped unverified for lack of a JS test runner
 * (blocker B4 in the QA final report): sections collapse, only one is open at a
 * time, the section holding the current page opens itself, and a section with a
 * single item renders as a plain link rather than a header. Everything the
 * sidebar needs from Inertia is read through usePage().props.auth, so one mock
 * of the page state drives the component and its children.
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

// A permission set that yields two multi-item sections (CRM, Marketing) and one
// single-item section (Portal).
const PERMS = ['crm.contact.read', 'crm.company.read', 'crm.lead.read', 'crm.deal.read', 'marketing.view', 'portal.access'];

beforeEach(() => {
    page.url = '/dashboard';
    page.props.auth.permissions = [];
});

describe('AppSidebar sections', () => {
    it('renders multi-item groups as collapsible headers and starts them closed on the dashboard', () => {
        renderSidebar('/dashboard', PERMS);

        // The section headers exist as buttons.
        expect(screen.getByRole('button', { name: /CRM/i })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /Marketing/i })).toBeInTheDocument();

        // Nothing is active on the dashboard, so no section is expanded: the
        // child links are not mounted.
        expect(screen.queryByRole('link', { name: 'Contacts' })).not.toBeInTheDocument();
        expect(screen.queryByRole('link', { name: 'Lists' })).not.toBeInTheDocument();
    });

    it('renders a single-item section as a plain link, not a header', () => {
        renderSidebar('/dashboard', PERMS);

        // Portal has one item (portal.access), so it is a top-level link and
        // there is no "Portal" collapsible header to click.
        expect(screen.getByRole('link', { name: /Client Portal/i })).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: /^Portal$/i })).not.toBeInTheDocument();
    });

    it('opens the section that holds the current page', () => {
        renderSidebar('/crm/contacts', PERMS);

        // Landing on a CRM page opens CRM, so its items are mounted...
        expect(screen.getByRole('link', { name: 'Contacts' })).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Deals' })).toBeInTheDocument();

        // ...while Marketing stays closed.
        expect(screen.queryByRole('link', { name: 'Lists' })).not.toBeInTheDocument();
    });

    it('opens only one section at a time', async () => {
        const user = userEvent.setup();
        renderSidebar('/dashboard', PERMS);

        await user.click(screen.getByRole('button', { name: /CRM/i }));
        expect(screen.getByRole('link', { name: 'Contacts' })).toBeInTheDocument();
        expect(screen.queryByRole('link', { name: 'Lists' })).not.toBeInTheDocument();

        // Opening Marketing must close CRM.
        await user.click(screen.getByRole('button', { name: /Marketing/i }));
        expect(screen.getByRole('link', { name: 'Lists' })).toBeInTheDocument();
        expect(screen.queryByRole('link', { name: 'Contacts' })).not.toBeInTheDocument();
    });

    it('marks the current page as the active item', () => {
        renderSidebar('/crm/contacts', PERMS);

        const contacts = screen.getByRole('link', { name: 'Contacts' });

        // The active item carries aria-current or the active data attribute the
        // sidebar button sets via isActive.
        expect(contacts).toHaveAttribute('data-active', 'true');
    });

    it('hides sections the user has no permission for', () => {
        // Two CRM permissions keep CRM a multi-item collapsible; Marketing and
        // Portal have no granted permission and must not appear at all.
        renderSidebar('/dashboard', ['crm.contact.read', 'crm.deal.read']);

        expect(screen.getByRole('button', { name: /CRM/i })).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: /Marketing/i })).not.toBeInTheDocument();
        expect(screen.queryByRole('link', { name: /Client Portal/i })).not.toBeInTheDocument();
    });

    it('collapses a section down to a plain link when only one of its items is permitted', () => {
        // With a single CRM permission, CRM is no longer a group - it renders as
        // one link, the same rule that makes Portal a link.
        renderSidebar('/dashboard', ['crm.contact.read']);

        expect(screen.queryByRole('button', { name: /^CRM$/i })).not.toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Contacts' })).toBeInTheDocument();
    });

    it('always shows the Dashboard link regardless of permissions', () => {
        renderSidebar('/dashboard', []);

        const dashboard = screen.getByRole('link', { name: 'Dashboard' });
        expect(dashboard).toBeInTheDocument();
        expect(dashboard).toHaveAttribute('href', '/dashboard');
    });

    it('resolves the longest-matching active item, not a prefix', () => {
        // /crm/contacts must activate Contacts, not a shorter prefix like a
        // hypothetical /crm dashboard - the bug the longest-match rule fixed.
        renderSidebar('/crm/contacts', PERMS);

        const crmSection = screen.getByRole('button', { name: /CRM/i }).closest('div');
        const contacts = within(crmSection as HTMLElement).getByRole('link', { name: 'Contacts' });

        expect(contacts).toHaveAttribute('data-active', 'true');
        // Companies is in the same section but must not be active.
        expect(within(crmSection as HTMLElement).getByRole('link', { name: 'Companies' })).toHaveAttribute('data-active', 'false');
    });
});
