import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'API tokens',
        href: '/settings/api-tokens',
    },
];

type ApiToken = {
    id: number;
    name: string;
    last_used_at: string | null;
    created_at: string;
};

type ApiTokensProps = {
    tokens: ApiToken[];
    plainTextToken: string | null;
};

const formatDate = (value: string | null): string => (value ? new Date(value).toLocaleString() : 'Never');

export default function ApiTokens({ tokens, plainTextToken }: ApiTokensProps) {
    const create = useForm({ name: '' });
    const revoke = useForm({});

    const submitCreate: FormEventHandler = (e) => {
        e.preventDefault();

        create.post(route('api-tokens.store'), {
            preserveScroll: true,
            onSuccess: () => create.reset(),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="API tokens" />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall
                        title="API tokens"
                        description="Personal access tokens allow scripts and integrations to authenticate against the Piotrack API"
                    />

                    {plainTextToken && (
                        <Alert>
                            <AlertTitle>Copy your new token now</AlertTitle>
                            <AlertDescription className="space-y-2">
                                <p>For security it will not be shown again.</p>
                                <code className="bg-muted block rounded px-2 py-1.5 font-mono text-xs break-all select-all">{plainTextToken}</code>
                            </AlertDescription>
                        </Alert>
                    )}

                    <form onSubmit={submitCreate} className="space-y-4">
                        <div className="grid max-w-sm gap-2">
                            <Label htmlFor="name">Token name</Label>
                            <Input
                                id="name"
                                value={create.data.name}
                                onChange={(e) => create.setData('name', e.target.value)}
                                placeholder="e.g. Reporting script"
                            />
                            <InputError message={create.errors.name} />
                        </div>

                        <Button disabled={create.processing}>Create token</Button>
                    </form>

                    <div className="space-y-3">
                        <HeadingSmall title="Active tokens" description="Revoking a token immediately blocks any integration using it" />

                        {tokens.length === 0 ? (
                            <p className="text-muted-foreground text-sm">No API tokens yet. Create your first token to integrate with the API.</p>
                        ) : (
                            <ul className="divide-y rounded-lg border">
                                {tokens.map((token) => (
                                    <li key={token.id} className="flex items-center justify-between gap-4 p-4">
                                        <div className="min-w-0">
                                            <p className="truncate font-medium">{token.name}</p>
                                            <p className="text-muted-foreground text-xs">
                                                Created {formatDate(token.created_at)} · Last used {formatDate(token.last_used_at)}
                                            </p>
                                        </div>

                                        <Dialog>
                                            <DialogTrigger asChild>
                                                <Button variant="destructive" size="sm">
                                                    Revoke
                                                </Button>
                                            </DialogTrigger>
                                            <DialogContent>
                                                <DialogTitle>Revoke "{token.name}"?</DialogTitle>
                                                <DialogDescription>
                                                    Any script or integration using this token will immediately lose access. This cannot be undone.
                                                </DialogDescription>
                                                <DialogFooter className="gap-2">
                                                    <DialogClose asChild>
                                                        <Button variant="secondary">Cancel</Button>
                                                    </DialogClose>
                                                    <Button
                                                        variant="destructive"
                                                        disabled={revoke.processing}
                                                        onClick={() =>
                                                            revoke.delete(route('api-tokens.destroy', token.id), {
                                                                preserveScroll: true,
                                                            })
                                                        }
                                                    >
                                                        Revoke token
                                                    </Button>
                                                </DialogFooter>
                                            </DialogContent>
                                        </Dialog>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
