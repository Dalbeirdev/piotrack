import Heading from '@/components/heading';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Analytics', href: '/analytics' }];

type Funnel = {
    leads: number;
    mqls: number;
    sqls: number;
    meetings: number;
    opportunities: number;
    proposals: number;
    qualified_pipeline: number;
    closed_won: number;
    closed_lost: number;
};

type Advertising = {
    impressions: number;
    clicks: number;
    spend: number;
    conversions: number;
    revenue: number;
    ctr: number;
    cpc: number;
    cpa: number;
    roas: number;
    conversion_rate: number;
};

type Seo = {
    tracked_keywords: number;
    top_three: number;
    page_one: number;
    visibility: number;
};

type Revenue = {
    mrr: number;
    arr: number;
    contract_value: number;
    ltv: number;
};

type Metrics = {
    funnel: Funnel;
    advertising: Advertising;
    seo: Seo;
    revenue: Revenue;
    sources: Record<string, number>;
};

const FUNNEL_STEPS: { key: keyof Funnel; label: string }[] = [
    { key: 'leads', label: 'Leads' },
    { key: 'mqls', label: 'MQLs' },
    { key: 'sqls', label: 'SQLs' },
    { key: 'meetings', label: 'Meetings' },
    { key: 'opportunities', label: 'Opportunities' },
    { key: 'proposals', label: 'Proposals' },
    { key: 'closed_won', label: 'Closed won' },
    { key: 'closed_lost', label: 'Closed lost' },
];

const SEO_STATS: { key: keyof Seo; label: string }[] = [
    { key: 'tracked_keywords', label: 'Tracked keywords' },
    { key: 'top_three', label: 'Top 3' },
    { key: 'page_one', label: 'Page one' },
    { key: 'visibility', label: 'Visibility' },
];

function money(cents: number): string {
    return `$${(cents / 100).toFixed(2)}`;
}

function revenueCards(revenue: Revenue): { label: string; value: string }[] {
    return [
        { label: 'MRR', value: money(revenue.mrr) },
        { label: 'ARR', value: money(revenue.arr) },
        { label: 'Contract value', value: money(revenue.contract_value) },
        { label: 'LTV', value: money(revenue.ltv) },
    ];
}

function adCards(ads: Advertising): { label: string; value: string | number }[] {
    return [
        { label: 'Spend', value: money(ads.spend) },
        { label: 'Revenue', value: money(ads.revenue) },
        { label: 'Impressions', value: ads.impressions },
        { label: 'Clicks', value: ads.clicks },
        { label: 'CTR', value: `${ads.ctr}%` },
        { label: 'CPC', value: money(ads.cpc) },
        { label: 'CPA', value: money(ads.cpa) },
        { label: 'ROAS', value: `${ads.roas}x` },
    ];
}

export default function AnalyticsDashboard({ metrics }: { metrics: Metrics }) {
    const sources = Object.entries(metrics.sources);
    const sourceTotal = sources.reduce((total, [, count]) => total + count, 0);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Analytics" />
            <div className="space-y-6 p-4">
                <Heading title="Analytics" description="Funnel, paid media, organic visibility and revenue in one view" />

                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    {revenueCards(metrics.revenue).map((card) => (
                        <Card key={card.label}>
                            <CardContent className="p-4">
                                <p className="text-muted-foreground text-sm">{card.label}</p>
                                <p className="text-2xl font-semibold">{card.value}</p>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <div>
                    <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                        <h3 className="text-sm font-medium">Acquisition funnel</h3>
                        <p className="text-muted-foreground text-sm">Qualified pipeline {money(metrics.funnel.qualified_pipeline)}</p>
                    </div>
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        {FUNNEL_STEPS.map((step) => (
                            <Card key={step.key}>
                                <CardContent className="p-4">
                                    <p className="text-muted-foreground text-sm">{step.label}</p>
                                    <p className="text-2xl font-semibold">{metrics.funnel[step.key]}</p>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                </div>

                <div>
                    <h3 className="mb-2 text-sm font-medium">Advertising</h3>
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-8">
                        {adCards(metrics.advertising).map((card) => (
                            <Card key={card.label}>
                                <CardContent className="p-4">
                                    <p className="text-muted-foreground text-sm">{card.label}</p>
                                    <p className="text-xl font-semibold">{card.value}</p>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                </div>

                <div>
                    <h3 className="mb-2 text-sm font-medium">Organic search</h3>
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        {SEO_STATS.map((stat) => (
                            <Card key={stat.key}>
                                <CardContent className="p-4">
                                    <p className="text-muted-foreground text-sm">{stat.label}</p>
                                    <p className="text-2xl font-semibold">{metrics.seo[stat.key]}</p>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                </div>

                <div>
                    <h3 className="mb-2 text-sm font-medium">Leads by channel</h3>
                    {sources.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No leads yet. Channels appear here once contacts are captured.</p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th className="p-3 font-medium">Channel</th>
                                        <th className="p-3 text-center font-medium">Leads</th>
                                        <th className="p-3 text-center font-medium">Share</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {sources.map(([channel, count]) => (
                                        <tr key={channel} className="hover:bg-muted/40">
                                            <td className="p-3 font-medium">{channel}</td>
                                            <td className="p-3 text-center">{count}</td>
                                            <td className="text-muted-foreground p-3 text-center">
                                                {sourceTotal > 0 ? `${Math.round((count / sourceTotal) * 100)}%` : '—'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
