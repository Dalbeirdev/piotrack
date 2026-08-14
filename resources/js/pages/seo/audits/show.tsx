import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

type Check = {
    key: string;
    label: string;
    status: 'pass' | 'warn' | 'fail';
    detail: string;
};

type Audit = {
    id: number;
    url: string;
    score: number;
    issues_count: number;
    fetched_status: number | null;
    checks: Check[];
    created_at: string | null;
};

function scoreText(score: number): string {
    if (score >= 80) return 'text-primary';
    if (score >= 50) return 'text-foreground';
    return 'text-destructive';
}

function checkVariant(status: Check['status']): 'default' | 'secondary' | 'destructive' {
    if (status === 'pass') return 'default';
    if (status === 'warn') return 'secondary';
    return 'destructive';
}

export default function SeoAuditShow({ audit }: { audit: Audit }) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'SEO Audits', href: '/seo/audits' },
        { title: audit.url, href: `/seo/audits/${audit.id}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Audit" />
            <div className="space-y-6 p-4">
                <div>
                    <Link href={route('seo.audits.index')} className="text-muted-foreground text-sm hover:underline">
                        ← Back to audits
                    </Link>
                </div>

                <Card>
                    <CardContent className="flex flex-wrap items-center justify-between gap-4 p-4">
                        <div className="space-y-1">
                            <p className="text-lg font-semibold break-all">{audit.url}</p>
                            <div className="text-muted-foreground flex flex-wrap items-center gap-2 text-sm">
                                <Badge variant="outline">HTTP {audit.fetched_status ?? '—'}</Badge>
                                <span>{audit.issues_count} issues</span>
                            </div>
                        </div>
                        <div className="text-right">
                            <p className={`text-5xl font-bold ${scoreText(audit.score)}`}>{audit.score}</p>
                            <p className="text-muted-foreground text-xs">Score</p>
                        </div>
                    </CardContent>
                </Card>

                <div>
                    <h3 className="mb-2 text-sm font-medium">Checks</h3>
                    {audit.checks.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No checks recorded for this audit.</p>
                    ) : (
                        <div className="divide-y overflow-hidden rounded-lg border">
                            {audit.checks.map((check) => (
                                <div key={check.key} className="flex items-start gap-3 p-3">
                                    <Badge variant={checkVariant(check.status)}>{check.status}</Badge>
                                    <div className="space-y-0.5">
                                        <p className="font-medium">{check.label}</p>
                                        <p className="text-muted-foreground text-sm">{check.detail}</p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
