import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { StatCard } from '@/components/stat-card';
import { render, screen } from '@testing-library/react';
import { Inbox } from 'lucide-react';
import { describe, expect, it } from 'vitest';

/**
 * The shared design-system primitives (audit §5, §14, §18, §28). These formalise
 * the page-header, KPI and empty-state patterns that 68+ pages each improvised,
 * so a page adopts one consistent component instead of re-inventing the markup.
 */

describe('PageHeader', () => {
    it('renders the title as the single page heading', () => {
        render(<PageHeader title="Contacts" />);

        const heading = screen.getByRole('heading', { level: 1 });
        expect(heading).toHaveTextContent('Contacts');
    });

    it('renders a concise description and the actions slot', () => {
        render(<PageHeader title="Contacts" description="Manage and qualify your customer relationships." actions={<button>Add Contact</button>} />);

        expect(screen.getByText('Manage and qualify your customer relationships.')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Add Contact' })).toBeInTheDocument();
    });

    it('renders the controls row (tabs, filters) below the title', () => {
        render(
            <PageHeader title="Contacts">
                <div data-testid="controls">Search…</div>
            </PageHeader>,
        );

        expect(screen.getByTestId('controls')).toBeInTheDocument();
    });
});

describe('StatCard', () => {
    it('shows the label and value', () => {
        render(<StatCard label="New Leads" value={124} />);

        expect(screen.getByText('New Leads')).toBeInTheDocument();
        expect(screen.getByText('124')).toBeInTheDocument();
    });

    it('colours an upward delta as positive and a downward delta as negative', () => {
        const { rerender } = render(<StatCard label="MRR" value="$31.4K" delta={{ value: '+13%', direction: 'up' }} />);
        expect(screen.getByText('+13%')).toHaveClass('text-emerald-600');

        rerender(<StatCard label="Churn" value="2.1%" delta={{ value: '-4%', direction: 'down' }} />);
        expect(screen.getByText('-4%')).toHaveClass('text-red-600');
    });

    it('renders no delta when none is given', () => {
        render(<StatCard label="Pipeline" value="$486K" />);

        expect(screen.getByText('$486K')).toBeInTheDocument();
        expect(screen.queryByText(/%$/)).not.toBeInTheDocument();
    });
});

describe('EmptyState', () => {
    it('renders the title, guidance and a call to action', () => {
        render(
            <EmptyState
                icon={Inbox}
                title="No opportunities yet"
                description="Convert a qualified lead to begin tracking pipeline revenue."
                action={<button>Create Opportunity</button>}
            />,
        );

        expect(screen.getByRole('heading', { name: 'No opportunities yet' })).toBeInTheDocument();
        expect(screen.getByText(/Convert a qualified lead/)).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Create Opportunity' })).toBeInTheDocument();
    });

    it('renders without an icon or action', () => {
        render(<EmptyState title="No results" />);

        expect(screen.getByRole('heading', { name: 'No results' })).toBeInTheDocument();
    });
});
