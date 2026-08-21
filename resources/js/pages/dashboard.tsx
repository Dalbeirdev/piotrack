import { OnboardingChecklist } from '@/components/onboarding-checklist';
import { PageHeader } from '@/components/page-header';
import { StatCard } from '@/components/stat-card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { CalendarCheck, DollarSign, Handshake, TrendingUp, UserPlus, Users } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];

type Onboarding = { steps: { key: string; label: string; done: boolean; url: string }[]; complete: boolean };
type Metrics = {
    leads: number;
    sqls: number;
    meetings: number;
    opportunities: number;
    qualified_pipeline: number;
    closed_won: number;
    mrr: number;
    arr: number;
};

/** Minor units (cents) to a compact dollar string: 5400000 -> $54,000. */
function money(minor: number): string {
    const dollars = Math.round(minor / 100);
    if (dollars >= 1000) {
        return `$${(dollars / 1000).toLocaleString('en-US', { maximumFractionDigits: 1 })}K`;
    }
    return `$${dollars.toLocaleString('en-US')}`;
}

export default function Dashboard({ onboarding, metrics, sources }: { onboarding: Onboarding; metrics: Metrics; sources: Record<string, number> }) {
    const sourceRows = Object.entries(sources).sort((a, b) => b[1] - a[1]);
    const sourceTotal = sourceRows.reduce((sum, [, n]) => sum + n, 0);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="space-y-6 p-4">
                <PageHeader title="Dashboard" description="Your growth at a glance — pipeline, revenue, and where leads are coming from." />

                {!onboarding.complete && <OnboardingChecklist onboarding={onboarding} />}

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard label="New Leads" value={metrics.leads.toLocaleString('en-US')} icon={UserPlus} />
                    <StatCard label="SQLs" value={metrics.sqls.toLocaleString('en-US')} icon={Users} />
                    <StatCard label="Meetings" value={metrics.meetings.toLocaleString('en-US')} icon={CalendarCheck} />
                    <StatCard label="Open Opportunities" value={metrics.opportunities.toLocaleString('en-US')} icon={Handshake} />
                    <StatCard label="Qualified Pipeline" value={money(metrics.qualified_pipeline)} icon={TrendingUp} />
                    <StatCard label="Customers Won" value={metrics.closed_won.toLocaleString('en-US')} icon={Handshake} />
                    <StatCard label="New MRR" value={money(metrics.mrr)} icon={DollarSign} />
                    <StatCard label="ARR" value={money(metrics.arr)} icon={DollarSign} />
                </div>

                <div className="border-border bg-card rounded-lg border">
                    <div className="border-border border-b px-4 py-3">
                        <h2 className="text-foreground text-sm font-semibold">Lead Sources</h2>
                    </div>
                    {sourceRows.length === 0 ? (
                        <p className="text-muted-foreground px-4 py-6 text-sm">No leads captured yet.</p>
                    ) : (
                        <ul className="divide-border divide-y">
                            {sourceRows.map(([channel, total]) => {
                                const pct = sourceTotal > 0 ? Math.round((total / sourceTotal) * 100) : 0;
                                return (
                                    <li key={channel} className="flex items-center gap-3 px-4 py-2.5">
                                        <span className="text-foreground w-28 shrink-0 text-sm font-medium capitalize">{channel}</span>
                                        <div className="bg-muted h-2 flex-1 overflow-hidden rounded-full">
                                            <div className="bg-primary h-full rounded-full" style={{ width: `${pct}%` }} />
                                        </div>
                                        <span className="text-muted-foreground w-16 shrink-0 text-right text-sm tabular-nums">
                                            {total} · {pct}%
                                        </span>
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
