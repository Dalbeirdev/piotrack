import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

type CampaignStats = {
    recipients: number;
    sent: number;
    opened: number;
    clicked: number;
    bounced: number;
    unsubscribed: number;
};

type Campaign = {
    id: number;
    name: string;
    channel: string;
    type: string | null;
    subject: string | null;
    from_name: string | null;
    from_email: string | null;
    body_html: string | null;
    body_text: string | null;
    status: string;
    marketing_list_id: number | null;
    stats: CampaignStats;
};

type ListOption = { id: number; name: string };

const STAT_CARDS: { key: keyof CampaignStats; label: string }[] = [
    { key: 'recipients', label: 'Recipients' },
    { key: 'sent', label: 'Sent' },
    { key: 'opened', label: 'Opened' },
    { key: 'clicked', label: 'Clicked' },
    { key: 'bounced', label: 'Bounced' },
    { key: 'unsubscribed', label: 'Unsubscribed' },
];

const textareaClass =
    'border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-28 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-hidden';

export default function CampaignShow({ campaign, lists }: { campaign: Campaign; lists: ListOption[] }) {
    const { can } = usePermissions();
    const canManage = can('marketing.campaigns.manage');
    const canSend = can('marketing.campaigns.send');

    const form = useForm<{
        name: string;
        subject: string;
        from_name: string;
        from_email: string;
        body_html: string;
        body_text: string;
        marketing_list_id: string;
    }>({
        name: campaign.name,
        subject: campaign.subject ?? '',
        from_name: campaign.from_name ?? '',
        from_email: campaign.from_email ?? '',
        body_html: campaign.body_html ?? '',
        body_text: campaign.body_text ?? '',
        marketing_list_id: campaign.marketing_list_id ? String(campaign.marketing_list_id) : '',
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Campaigns', href: '/marketing/campaigns' },
        { title: campaign.name, href: `/marketing/campaigns/${campaign.id}` },
    ];

    const save: FormEventHandler = (e) => {
        e.preventDefault();
        form.patch(route('marketing.campaigns.update', campaign.id), { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={campaign.name} />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div className="flex items-center gap-2">
                        <Heading title={campaign.name} description={campaign.channel} />
                        <Badge variant={campaign.status === 'sent' ? 'default' : 'secondary'}>{campaign.status}</Badge>
                    </div>
                    {canSend && (
                        <Button
                            disabled={campaign.status === 'sent'}
                            onClick={() => router.post(route('marketing.campaigns.send', campaign.id), {}, { preserveScroll: true })}
                        >
                            Send campaign
                        </Button>
                    )}
                </div>

                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                    {STAT_CARDS.map((card) => (
                        <Card key={card.key}>
                            <CardContent className="p-4">
                                <p className="text-muted-foreground text-sm">{card.label}</p>
                                <p className="text-2xl font-semibold">{campaign.stats[card.key]}</p>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <Card>
                    <CardContent className="p-4">
                        <form onSubmit={save} className="space-y-3">
                            <div className="grid gap-1">
                                <Label htmlFor="name">Name</Label>
                                <Input id="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                                <InputError message={form.errors.name} />
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor="subject">Subject</Label>
                                <Input id="subject" value={form.data.subject} onChange={(e) => form.setData('subject', e.target.value)} />
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <div className="grid gap-1">
                                    <Label htmlFor="from_name">From name</Label>
                                    <Input id="from_name" value={form.data.from_name} onChange={(e) => form.setData('from_name', e.target.value)} />
                                </div>
                                <div className="grid gap-1">
                                    <Label htmlFor="from_email">From email</Label>
                                    <Input
                                        id="from_email"
                                        type="email"
                                        value={form.data.from_email}
                                        onChange={(e) => form.setData('from_email', e.target.value)}
                                    />
                                </div>
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor="marketing_list_id">List</Label>
                                <Select value={form.data.marketing_list_id} onValueChange={(v) => form.setData('marketing_list_id', v)}>
                                    <SelectTrigger id="marketing_list_id">
                                        <SelectValue placeholder="Select a list" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {lists.map((list) => (
                                            <SelectItem key={list.id} value={String(list.id)}>
                                                {list.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor="body_html">Body HTML</Label>
                                <textarea
                                    id="body_html"
                                    className={textareaClass}
                                    value={form.data.body_html}
                                    onChange={(e) => form.setData('body_html', e.target.value)}
                                />
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor="body_text">Body text</Label>
                                <textarea
                                    id="body_text"
                                    className={textareaClass}
                                    value={form.data.body_text}
                                    onChange={(e) => form.setData('body_text', e.target.value)}
                                />
                            </div>
                            <p className="text-muted-foreground text-xs">
                                Use {'{{first_name}}'}, {'{{last_name}}'}, {'{{full_name}}'}, {'{{email}}'}, {'{{company}}'}
                            </p>
                            {canManage && (
                                <Button type="submit" disabled={form.processing}>
                                    Save
                                </Button>
                            )}
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
