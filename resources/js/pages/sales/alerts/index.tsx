import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Alerts', href: '/sales/alerts' }];

type Rule = {
    id: number;
    name: string;
    trigger: string;
    threshold: number;
    channel: string;
    is_active: boolean;
};

type Alert = {
    id: number;
    type: string;
    message: string;
    is_read: boolean;
    contact: string | null;
    created_at: string | null;
};

type Trigger = 'score_threshold' | 'high_intent' | 'meeting_request' | 'repeat_visit' | 'bottom_funnel';
type Channel = 'in_app' | 'email';

const TRIGGERS: Trigger[] = ['score_threshold', 'high_intent', 'meeting_request', 'repeat_visit', 'bottom_funnel'];
const CHANNELS: Channel[] = ['in_app', 'email'];

function formatTime(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleString();
}

function NewRuleDialog() {
    const [open, setOpen] = useState(false);
    const form = useForm<{ name: string; trigger: Trigger; threshold: string; channel: Channel }>({
        name: '',
        trigger: 'score_threshold',
        threshold: '',
        channel: 'in_app',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.transform((data) => ({ ...data, threshold: Number(data.threshold) }));
        form.post(route('sales.alerts.store'), {
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
                <Button>New rule</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>New alert rule</DialogTitle>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid gap-1">
                        <Label htmlFor="alert_name">Name</Label>
                        <Input id="alert_name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1">
                            <Label htmlFor="alert_trigger">Trigger</Label>
                            <Select value={form.data.trigger} onValueChange={(v) => form.setData('trigger', v as Trigger)}>
                                <SelectTrigger id="alert_trigger">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {TRIGGERS.map((trigger) => (
                                        <SelectItem key={trigger} value={trigger}>
                                            {trigger}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.trigger} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="alert_threshold">Threshold</Label>
                            <Input
                                id="alert_threshold"
                                type="number"
                                value={form.data.threshold}
                                onChange={(e) => form.setData('threshold', e.target.value)}
                            />
                            <InputError message={form.errors.threshold} />
                        </div>
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="alert_channel">Channel</Label>
                        <Select value={form.data.channel} onValueChange={(v) => form.setData('channel', v as Channel)}>
                            <SelectTrigger id="alert_channel">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {CHANNELS.map((channel) => (
                                    <SelectItem key={channel} value={channel}>
                                        {channel}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.channel} />
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

export default function Alerts({ rules, alerts }: { rules: Rule[]; alerts: Alert[] }) {
    const { can } = usePermissions();
    const canManage = can('sales.alerts.manage');

    const removeRule = (id: number) => router.delete(route('sales.alerts.destroy', id), { preserveScroll: true });
    const markRead = (id: number) => router.post(route('sales.alerts.read', id), {}, { preserveScroll: true });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Alerts" />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Heading title="Sales alerts" description="Alert rules and the alerts they trigger" />
                    {canManage && <NewRuleDialog />}
                </div>

                <div>
                    <h3 className="mb-2 text-sm font-medium">Alert rules</h3>
                    {rules.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No rules yet. Create a rule to raise alerts automatically.</p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th className="p-3 font-medium">Name</th>
                                        <th className="p-3 font-medium">Trigger</th>
                                        <th className="p-3 text-center font-medium">Threshold</th>
                                        <th className="p-3 font-medium">Channel</th>
                                        <th className="p-3 text-center font-medium">Active</th>
                                        {canManage && <th className="p-3 text-right font-medium">Actions</th>}
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {rules.map((rule) => (
                                        <tr key={rule.id} className="hover:bg-muted/40">
                                            <td className="p-3 font-medium">{rule.name}</td>
                                            <td className="p-3">
                                                <Badge variant="outline">{rule.trigger}</Badge>
                                            </td>
                                            <td className="p-3 text-center">{rule.threshold}</td>
                                            <td className="text-muted-foreground p-3">{rule.channel}</td>
                                            <td className="p-3 text-center">
                                                {rule.is_active ? '✓' : <span className="text-muted-foreground">—</span>}
                                            </td>
                                            {canManage && (
                                                <td className="p-3">
                                                    <div className="flex justify-end">
                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                            className="text-destructive"
                                                            onClick={() => removeRule(rule.id)}
                                                        >
                                                            Delete
                                                        </Button>
                                                    </div>
                                                </td>
                                            )}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                <div>
                    <h3 className="mb-2 text-sm font-medium">Alerts inbox</h3>
                    {alerts.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No alerts yet. Alerts appear here as your rules fire.</p>
                    ) : (
                        <div className="divide-y rounded-lg border">
                            {alerts.map((alert) => (
                                <div key={alert.id} className="flex items-center justify-between gap-3 p-3">
                                    <div className="flex min-w-0 items-center gap-2">
                                        <Badge variant="outline">{alert.type}</Badge>
                                        <span className="truncate text-sm">{alert.message}</span>
                                        {alert.contact && <span className="text-muted-foreground text-xs">{alert.contact}</span>}
                                    </div>
                                    <div className="flex shrink-0 items-center gap-2">
                                        <span className="text-muted-foreground text-xs">{formatTime(alert.created_at)}</span>
                                        {alert.is_read ? (
                                            <span className="text-muted-foreground text-xs">Read</span>
                                        ) : (
                                            <Button size="sm" variant="secondary" onClick={() => markRead(alert.id)}>
                                                Mark read
                                            </Button>
                                        )}
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
