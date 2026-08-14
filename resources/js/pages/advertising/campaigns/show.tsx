import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

type Kpi = {
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

type Ad = {
    id: number;
    name: string;
    headline: string | null;
    status: string;
};

type Keyword = {
    id: number;
    phrase: string;
    match_type: string;
    is_negative: boolean;
};

type Group = {
    id: number;
    name: string;
    status: string;
    bid_strategy: string;
    ads: Ad[];
    keywords: Keyword[];
};

type Campaign = {
    id: number;
    name: string;
    platform: string;
    type: string | null;
    objective: string;
    status: string;
    daily_budget: number;
    total_budget: number;
};

type Metric = {
    date: string;
    impressions: number;
    clicks: number;
    spend: number;
    conversions: number;
    revenue: number;
};

type BidStrategy = 'manual_cpc' | 'maximize_conversions' | 'target_cpa';
type MatchType = 'broad' | 'phrase' | 'exact';

const BID_STRATEGIES: BidStrategy[] = ['manual_cpc', 'maximize_conversions', 'target_cpa'];
const MATCH_TYPES: MatchType[] = ['broad', 'phrase', 'exact'];

const textareaClass =
    'border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-28 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-hidden';

function money(cents: number): string {
    return `$${(cents / 100).toFixed(2)}`;
}

function statusVariant(status: string): 'default' | 'secondary' {
    return status === 'active' ? 'default' : 'secondary';
}

function kpiCards(kpi: Kpi): { label: string; value: string | number }[] {
    return [
        { label: 'Spend', value: money(kpi.spend) },
        { label: 'Impressions', value: kpi.impressions },
        { label: 'Clicks', value: kpi.clicks },
        { label: 'CTR', value: `${kpi.ctr}%` },
        { label: 'Conversions', value: kpi.conversions },
        { label: 'CPA', value: money(kpi.cpa) },
        { label: 'ROAS', value: `${kpi.roas}x` },
    ];
}

function AddAdGroupDialog({ campaignId }: { campaignId: number }) {
    const [open, setOpen] = useState(false);
    const form = useForm<{ name: string; bid_strategy: BidStrategy; bid_amount: string }>({
        name: '',
        bid_strategy: 'manual_cpc',
        bid_amount: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.transform((data) => ({
            ...data,
            bid_amount: data.bid_amount ? Math.round(Number(data.bid_amount) * 100) : null,
        }));
        form.post(route('ads.groups.store', campaignId), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm">Add ad group</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Add ad group</DialogTitle>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid gap-1">
                        <Label htmlFor="group_name">Name</Label>
                        <Input id="group_name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1">
                            <Label htmlFor="bid_strategy">Bid strategy</Label>
                            <Select value={form.data.bid_strategy} onValueChange={(v) => form.setData('bid_strategy', v as BidStrategy)}>
                                <SelectTrigger id="bid_strategy">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {BID_STRATEGIES.map((strategy) => (
                                        <SelectItem key={strategy} value={strategy}>
                                            {strategy}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="bid_amount">Bid amount ($)</Label>
                            <Input
                                id="bid_amount"
                                type="number"
                                min="0"
                                step="0.01"
                                value={form.data.bid_amount}
                                onChange={(e) => form.setData('bid_amount', e.target.value)}
                            />
                            <InputError message={form.errors.bid_amount} />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Create
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function AddAdDialog({ groupId }: { groupId: number }) {
    const [open, setOpen] = useState(false);
    const form = useForm<{ name: string; headline: string; body: string; cta: string; destination_url: string }>({
        name: '',
        headline: '',
        body: '',
        cta: '',
        destination_url: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('ads.ads.store', groupId), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="secondary">
                    Add ad
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Add ad</DialogTitle>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid gap-1">
                        <Label htmlFor="ad_name">Name</Label>
                        <Input id="ad_name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="ad_headline">Headline</Label>
                        <Input id="ad_headline" value={form.data.headline} onChange={(e) => form.setData('headline', e.target.value)} />
                        <InputError message={form.errors.headline} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="ad_body">Body</Label>
                        <textarea
                            id="ad_body"
                            className={textareaClass}
                            value={form.data.body}
                            onChange={(e) => form.setData('body', e.target.value)}
                        />
                        <InputError message={form.errors.body} />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1">
                            <Label htmlFor="ad_cta">CTA</Label>
                            <Input id="ad_cta" value={form.data.cta} onChange={(e) => form.setData('cta', e.target.value)} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="ad_destination_url">Destination URL</Label>
                            <Input
                                id="ad_destination_url"
                                type="url"
                                value={form.data.destination_url}
                                onChange={(e) => form.setData('destination_url', e.target.value)}
                            />
                            <InputError message={form.errors.destination_url} />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Create
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function AddKeywordDialog({ groupId }: { groupId: number }) {
    const [open, setOpen] = useState(false);
    const form = useForm<{ phrase: string; match_type: MatchType; is_negative: boolean }>({
        phrase: '',
        match_type: 'broad',
        is_negative: false,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('ads.keywords.store', groupId), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="secondary">
                    Add keyword
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Add keyword</DialogTitle>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid gap-1">
                        <Label htmlFor="keyword_phrase">Phrase</Label>
                        <Input id="keyword_phrase" value={form.data.phrase} onChange={(e) => form.setData('phrase', e.target.value)} />
                        <InputError message={form.errors.phrase} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="match_type">Match type</Label>
                        <Select value={form.data.match_type} onValueChange={(v) => form.setData('match_type', v as MatchType)}>
                            <SelectTrigger id="match_type">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {MATCH_TYPES.map((type) => (
                                    <SelectItem key={type} value={type}>
                                        {type}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox checked={form.data.is_negative} onCheckedChange={(v) => form.setData('is_negative', v === true)} />
                        Negative keyword
                    </label>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Create
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function AdGroupCard({ group, canManage }: { group: Group; canManage: boolean }) {
    const deleteGroup = () => router.delete(route('ads.groups.destroy', group.id), { preserveScroll: true });
    const deleteAd = (id: number) => router.delete(route('ads.ads.destroy', id), { preserveScroll: true });
    const deleteKeyword = (id: number) => router.delete(route('ads.keywords.destroy', id), { preserveScroll: true });

    return (
        <Card>
            <CardContent className="space-y-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div className="flex items-center gap-2">
                        <span className="font-medium">{group.name}</span>
                        <Badge variant={statusVariant(group.status)}>{group.status}</Badge>
                        <span className="text-muted-foreground text-xs">{group.bid_strategy}</span>
                    </div>
                    {canManage && (
                        <div className="flex flex-wrap gap-2">
                            <AddAdDialog groupId={group.id} />
                            <AddKeywordDialog groupId={group.id} />
                            <Button size="sm" variant="ghost" className="text-destructive" onClick={deleteGroup}>
                                Delete group
                            </Button>
                        </div>
                    )}
                </div>

                <div>
                    <h4 className="text-muted-foreground mb-2 text-xs font-medium uppercase">Ads</h4>
                    {group.ads.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No ads yet. Add an ad to this group.</p>
                    ) : (
                        <ul className="divide-y rounded-lg border">
                            {group.ads.map((ad) => (
                                <li key={ad.id} className="flex items-center justify-between gap-3 p-3">
                                    <div>
                                        <p className="text-sm font-medium">{ad.name}</p>
                                        {ad.headline && <p className="text-muted-foreground text-xs">{ad.headline}</p>}
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Badge variant={statusVariant(ad.status)}>{ad.status}</Badge>
                                        {canManage && (
                                            <Button size="sm" variant="ghost" className="text-destructive" onClick={() => deleteAd(ad.id)}>
                                                Delete
                                            </Button>
                                        )}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>

                <div>
                    <h4 className="text-muted-foreground mb-2 text-xs font-medium uppercase">Keywords</h4>
                    {group.keywords.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No keywords yet. Add a keyword to this group.</p>
                    ) : (
                        <ul className="divide-y rounded-lg border">
                            {group.keywords.map((keyword) => (
                                <li key={keyword.id} className="flex items-center justify-between gap-3 p-3">
                                    <div className="flex items-center gap-2">
                                        <span className="text-sm">{keyword.phrase}</span>
                                        <Badge variant="outline">{keyword.match_type}</Badge>
                                        {keyword.is_negative && <Badge variant="destructive">Negative</Badge>}
                                    </div>
                                    {canManage && (
                                        <Button size="sm" variant="ghost" className="text-destructive" onClick={() => deleteKeyword(keyword.id)}>
                                            Delete
                                        </Button>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}

export default function CampaignShow({ campaign, groups, kpi, metrics }: { campaign: Campaign; groups: Group[]; kpi: Kpi; metrics: Metric[] }) {
    const { can } = usePermissions();
    const canManage = can('ads.campaigns.manage');

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Campaigns', href: '/ads/campaigns' },
        { title: campaign.name, href: `/ads/campaigns/${campaign.id}` },
    ];

    const setStatus = (status: string) => router.post(route('ads.campaigns.status', campaign.id), { status }, { preserveScroll: true });
    const refreshMetrics = () => router.post(route('ads.campaigns.refresh-metrics', campaign.id), {}, { preserveScroll: true });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={campaign.name} />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div className="flex items-center gap-2">
                        <Heading title={campaign.name} description={campaign.objective} />
                        <Badge variant="outline">{campaign.platform}</Badge>
                        <Badge variant={statusVariant(campaign.status)}>{campaign.status}</Badge>
                    </div>
                    {canManage && (
                        <div className="flex flex-wrap gap-2">
                            <Button size="sm" variant="secondary" disabled={campaign.status === 'active'} onClick={() => setStatus('active')}>
                                Activate
                            </Button>
                            <Button size="sm" variant="secondary" disabled={campaign.status === 'paused'} onClick={() => setStatus('paused')}>
                                Pause
                            </Button>
                            <Button size="sm" variant="secondary" disabled={campaign.status === 'ended'} onClick={() => setStatus('ended')}>
                                End
                            </Button>
                            <Button size="sm" onClick={refreshMetrics}>
                                Refresh metrics
                            </Button>
                        </div>
                    )}
                </div>

                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-7">
                    {kpiCards(kpi).map((card) => (
                        <Card key={card.label}>
                            <CardContent className="p-4">
                                <p className="text-muted-foreground text-sm">{card.label}</p>
                                <p className="text-2xl font-semibold">{card.value}</p>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <div className="space-y-3">
                    <div className="flex items-center justify-between gap-2">
                        <h3 className="text-sm font-medium">Ad groups</h3>
                        {canManage && <AddAdGroupDialog campaignId={campaign.id} />}
                    </div>
                    {groups.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No ad groups yet. Add an ad group to organize your ads and keywords.</p>
                    ) : (
                        <div className="space-y-3">
                            {groups.map((group) => (
                                <AdGroupCard key={group.id} group={group} canManage={canManage} />
                            ))}
                        </div>
                    )}
                </div>

                <div>
                    <h3 className="mb-2 text-sm font-medium">Metrics</h3>
                    {metrics.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No metrics yet. Use &ldquo;Refresh metrics&rdquo; to pull the latest data.</p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th className="p-3 font-medium">Date</th>
                                        <th className="p-3 text-center font-medium">Impressions</th>
                                        <th className="p-3 text-center font-medium">Clicks</th>
                                        <th className="p-3 text-center font-medium">Spend</th>
                                        <th className="p-3 text-center font-medium">Conversions</th>
                                        <th className="p-3 text-center font-medium">Revenue</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {metrics.map((metric) => (
                                        <tr key={metric.date} className="hover:bg-muted/40">
                                            <td className="p-3">{metric.date}</td>
                                            <td className="p-3 text-center">{metric.impressions}</td>
                                            <td className="p-3 text-center">{metric.clicks}</td>
                                            <td className="p-3 text-center">{money(metric.spend)}</td>
                                            <td className="p-3 text-center">{metric.conversions}</td>
                                            <td className="p-3 text-center">{money(metric.revenue)}</td>
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
