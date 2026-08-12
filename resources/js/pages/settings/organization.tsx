import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types';
import { Transition } from '@headlessui/react';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Organization settings', href: '/settings/organization' }];

type OrganizationProps = {
    organization: { id: number; name: string; slug: string };
};

export default function OrganizationSettings({ organization }: OrganizationProps) {
    const { can } = usePermissions();
    const form = useForm({ name: organization.name });
    const del = useForm({ name: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.patch(route('organization.update'), { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Organization settings" />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall title="Organization" description="Update your organization's profile" />

                    <form onSubmit={submit} className="space-y-6">
                        <div className="grid gap-2">
                            <Label htmlFor="name">Organization name</Label>
                            <Input
                                id="name"
                                value={form.data.name}
                                onChange={(e) => form.setData('name', e.target.value)}
                                disabled={!can('organization.update')}
                            />
                            <InputError message={form.errors.name} />
                        </div>

                        {can('organization.update') && (
                            <div className="flex items-center gap-4">
                                <Button disabled={form.processing}>Save</Button>
                                <Transition
                                    show={form.recentlySuccessful}
                                    enter="transition ease-in-out"
                                    enterFrom="opacity-0"
                                    leave="transition ease-in-out"
                                    leaveTo="opacity-0"
                                >
                                    <p className="text-sm text-neutral-600">Saved</p>
                                </Transition>
                            </div>
                        )}
                    </form>
                </div>

                {can('organization.delete') && (
                    <div className="mt-10 space-y-4 rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-900/40 dark:bg-red-950/20">
                        <HeadingSmall title="Delete organization" description="This permanently removes the organization and all of its data" />

                        <Dialog>
                            <DialogTrigger asChild>
                                <Button variant="destructive">Delete organization</Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogTitle>Delete {organization.name}?</DialogTitle>
                                <DialogDescription>
                                    This cannot be undone. All members lose access. Type the organization name <strong>{organization.name}</strong> to
                                    confirm.
                                </DialogDescription>
                                <form
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        del.delete(route('organization.destroy'), { preserveScroll: true });
                                    }}
                                    className="space-y-4"
                                >
                                    <div className="grid gap-2">
                                        <Label htmlFor="confirm_name" className="sr-only">
                                            Organization name
                                        </Label>
                                        <Input
                                            id="confirm_name"
                                            value={del.data.name}
                                            onChange={(e) => del.setData('name', e.target.value)}
                                            placeholder={organization.name}
                                            autoComplete="off"
                                        />
                                        <InputError message={del.errors.name} />
                                    </div>
                                    <DialogFooter className="gap-2">
                                        <DialogClose asChild>
                                            <Button type="button" variant="secondary">
                                                Cancel
                                            </Button>
                                        </DialogClose>
                                        <Button type="submit" variant="destructive" disabled={del.processing}>
                                            Delete organization
                                        </Button>
                                    </DialogFooter>
                                </form>
                            </DialogContent>
                        </Dialog>
                    </div>
                )}
            </SettingsLayout>
        </AppLayout>
    );
}
