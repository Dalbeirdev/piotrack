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

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Enablement', href: '/sales/enablement' }];

type Asset = {
    id: number;
    type: string;
    title: string;
    description: string | null;
    url: string | null;
};

type Play = {
    id: number;
    name: string;
    description: string | null;
    target_segment: string | null;
    steps: unknown[];
};

const textareaClass =
    'border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-28 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-hidden';

function NewAssetDialog({ types }: { types: string[] }) {
    const [open, setOpen] = useState(false);
    const form = useForm<{ type: string; title: string; description: string; content: string; url: string }>({
        type: types[0] ?? '',
        title: '',
        description: '',
        content: '',
        url: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('sales.enablement.assets.store'), {
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
                <Button>New asset</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>New asset</DialogTitle>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1">
                            <Label htmlFor="asset_type">Type</Label>
                            <Select value={form.data.type} onValueChange={(v) => form.setData('type', v)}>
                                <SelectTrigger id="asset_type">
                                    <SelectValue placeholder="Select a type" />
                                </SelectTrigger>
                                <SelectContent>
                                    {types.map((type) => (
                                        <SelectItem key={type} value={type}>
                                            {type}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.type} />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="asset_title">Title</Label>
                            <Input id="asset_title" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                            <InputError message={form.errors.title} />
                        </div>
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="asset_description">Description</Label>
                        <textarea
                            id="asset_description"
                            className={textareaClass}
                            value={form.data.description}
                            onChange={(e) => form.setData('description', e.target.value)}
                        />
                        <InputError message={form.errors.description} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="asset_content">Content</Label>
                        <textarea
                            id="asset_content"
                            className={textareaClass}
                            value={form.data.content}
                            onChange={(e) => form.setData('content', e.target.value)}
                        />
                        <InputError message={form.errors.content} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="asset_url">URL</Label>
                        <Input id="asset_url" type="url" value={form.data.url} onChange={(e) => form.setData('url', e.target.value)} />
                        <InputError message={form.errors.url} />
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Add
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function NewPlayDialog() {
    const [open, setOpen] = useState(false);
    const form = useForm<{ name: string; description: string; target_segment: string }>({
        name: '',
        description: '',
        target_segment: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('sales.enablement.plays.store'), {
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
                <Button size="sm">New play</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>New play</DialogTitle>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid gap-1">
                        <Label htmlFor="play_name">Name</Label>
                        <Input id="play_name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="play_target_segment">Target segment</Label>
                        <Input
                            id="play_target_segment"
                            value={form.data.target_segment}
                            onChange={(e) => form.setData('target_segment', e.target.value)}
                        />
                        <InputError message={form.errors.target_segment} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="play_description">Description</Label>
                        <textarea
                            id="play_description"
                            className={textareaClass}
                            value={form.data.description}
                            onChange={(e) => form.setData('description', e.target.value)}
                        />
                        <InputError message={form.errors.description} />
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

export default function Enablement({ assets, plays, types }: { assets: Asset[]; plays: Play[]; types: string[] }) {
    const { can } = usePermissions();
    const canManage = can('sales.enablement.manage');

    const removeAsset = (id: number) => router.delete(route('sales.enablement.assets.destroy', id), { preserveScroll: true });
    const removePlay = (id: number) => router.delete(route('sales.enablement.plays.destroy', id), { preserveScroll: true });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Enablement" />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Heading title="Sales enablement" description="Assets and plays that help reps sell" />
                    {canManage && <NewAssetDialog types={types} />}
                </div>

                <div>
                    <h3 className="mb-2 text-sm font-medium">Assets library</h3>
                    {assets.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No assets yet. Add a deck, one-pager, or script to get started.</p>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/50 text-muted-foreground">
                                    <tr>
                                        <th className="p-3 font-medium">Type</th>
                                        <th className="p-3 font-medium">Title</th>
                                        <th className="p-3 font-medium">Description</th>
                                        <th className="p-3 font-medium">Link</th>
                                        {canManage && <th className="p-3 text-right font-medium">Actions</th>}
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {assets.map((asset) => (
                                        <tr key={asset.id} className="hover:bg-muted/40">
                                            <td className="p-3">
                                                <Badge variant="outline">{asset.type}</Badge>
                                            </td>
                                            <td className="p-3 font-medium">{asset.title}</td>
                                            <td className="text-muted-foreground p-3">
                                                <p className="max-w-xs truncate">{asset.description ?? '—'}</p>
                                            </td>
                                            <td className="p-3">
                                                {asset.url ? (
                                                    <a href={asset.url} target="_blank" rel="noreferrer" className="break-all hover:underline">
                                                        {asset.url}
                                                    </a>
                                                ) : (
                                                    <span className="text-muted-foreground">—</span>
                                                )}
                                            </td>
                                            {canManage && (
                                                <td className="p-3">
                                                    <div className="flex justify-end">
                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                            className="text-destructive"
                                                            onClick={() => removeAsset(asset.id)}
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
                    <div className="mb-2 flex items-center justify-between gap-2">
                        <h3 className="text-sm font-medium">Plays</h3>
                        {canManage && <NewPlayDialog />}
                    </div>
                    {plays.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No plays yet. Create a play to guide reps through a motion.</p>
                    ) : (
                        <div className="divide-y rounded-lg border">
                            {plays.map((play) => (
                                <div key={play.id} className="flex items-start justify-between gap-3 p-3">
                                    <div className="min-w-0">
                                        <div className="flex items-center gap-2">
                                            <span className="text-sm font-medium">{play.name}</span>
                                            {play.target_segment && <Badge variant="outline">{play.target_segment}</Badge>}
                                            <span className="text-muted-foreground text-xs">{play.steps.length} steps</span>
                                        </div>
                                        {play.description && <p className="text-muted-foreground mt-0.5 text-xs">{play.description}</p>}
                                    </div>
                                    {canManage && (
                                        <Button size="sm" variant="ghost" className="text-destructive" onClick={() => removePlay(play.id)}>
                                            Delete
                                        </Button>
                                    )}
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
