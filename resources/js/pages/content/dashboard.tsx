import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Content', href: '/content' }];

type Stats = {
    pieces: number;
    published: number;
    social_posts: number;
    placements: number;
};

type Reviews = {
    count: number;
    average: number;
    by_sentiment: { positive: number; neutral: number; negative: number };
    by_source: Record<string, number>;
};

type RecentPiece = {
    id: number;
    title: string;
    content_type: string;
    status: string;
    optimization_score: number;
};

function statusVariant(status: string): 'default' | 'secondary' {
    return status === 'published' ? 'default' : 'secondary';
}

export default function ContentDashboard({
    stats,
    byStatus,
    reviews,
    recentPieces,
}: {
    stats: Stats;
    byStatus: Record<string, number>;
    reviews: Reviews;
    recentPieces: RecentPiece[];
}) {
    const kpiCards: { label: string; value: string | number }[] = [
        { label: 'Pieces', value: stats.pieces },
        { label: 'Published', value: stats.published },
        { label: 'Social posts', value: stats.social_posts },
        { label: 'Placements', value: stats.placements },
        { label: 'Reviews', value: `${reviews.average} ★` },
    ];

    const statusEntries = Object.entries(byStatus);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Content" />
            <div className="space-y-6 p-4">
                <Heading title="Content" description="Content, social, reputation, and authority at a glance" />

                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                    {kpiCards.map((card) => (
                        <Card key={card.label}>
                            <CardContent className="p-4">
                                <p className="text-muted-foreground text-sm">{card.label}</p>
                                <p className="text-2xl font-semibold">{card.value}</p>
                                {card.label === 'Reviews' && <p className="text-muted-foreground text-xs">{reviews.count} total</p>}
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <div>
                        <h3 className="mb-2 text-sm font-medium">By status</h3>
                        {statusEntries.length === 0 ? (
                            <p className="text-muted-foreground text-sm">No content yet. Create a piece to start planning.</p>
                        ) : (
                            <div className="divide-y rounded-lg border">
                                {statusEntries.map(([status, count]) => (
                                    <div key={status} className="flex items-center justify-between gap-3 p-3">
                                        <Badge variant={statusVariant(status)}>{status}</Badge>
                                        <span className="text-sm font-medium">{count}</span>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>

                    <div>
                        <h3 className="mb-2 text-sm font-medium">Review sentiment</h3>
                        <div className="grid grid-cols-3 gap-3">
                            <Card>
                                <CardContent className="p-4">
                                    <p className="text-muted-foreground text-sm">Positive</p>
                                    <p className="text-2xl font-semibold">{reviews.by_sentiment.positive}</p>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardContent className="p-4">
                                    <p className="text-muted-foreground text-sm">Neutral</p>
                                    <p className="text-2xl font-semibold">{reviews.by_sentiment.neutral}</p>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardContent className="p-4">
                                    <p className="text-muted-foreground text-sm">Negative</p>
                                    <p className="text-2xl font-semibold">{reviews.by_sentiment.negative}</p>
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </div>

                <div>
                    <h3 className="mb-2 text-sm font-medium">Recent content</h3>
                    {recentPieces.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No content yet. Create a piece to start planning.</p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th className="p-3 font-medium">Title</th>
                                        <th className="p-3 font-medium">Type</th>
                                        <th className="p-3 font-medium">Status</th>
                                        <th className="p-3 text-center font-medium">Score</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {recentPieces.map((piece) => (
                                        <tr key={piece.id} className="hover:bg-muted/40">
                                            <td className="p-3">
                                                <Link href={route('content.pieces.show', piece.id)} className="font-medium hover:underline">
                                                    {piece.title}
                                                </Link>
                                            </td>
                                            <td className="p-3">
                                                <Badge variant="outline">{piece.content_type}</Badge>
                                            </td>
                                            <td className="p-3">
                                                <Badge variant={statusVariant(piece.status)}>{piece.status}</Badge>
                                            </td>
                                            <td className="p-3 text-center">{piece.optimization_score}/100</td>
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
