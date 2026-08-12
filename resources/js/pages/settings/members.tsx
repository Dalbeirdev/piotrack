import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Members', href: '/settings/members' }];

type Member = { id: number; name: string; email: string; role: string; status: string };
type Invitation = { id: number; email: string; role: string; expires_at: string };
type RoleOption = { value: string; label: string };

type MembersProps = {
    members: Member[];
    invitations: Invitation[];
    assignableRoles: RoleOption[];
};

export default function Members({ members, invitations, assignableRoles }: MembersProps) {
    const { can } = usePermissions();
    const invite = useForm({ email: '', role: 'viewer' });

    const submitInvite: FormEventHandler = (e) => {
        e.preventDefault();
        invite.post(route('invitations.store'), { preserveScroll: true, onSuccess: () => invite.reset() });
    };

    const roleLabel = (value: string) => assignableRoles.find((r) => r.value === value)?.label ?? value;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Members" />

            <SettingsLayout>
                <div className="space-y-8">
                    <div className="space-y-4">
                        <HeadingSmall title="Members" description="People with access to this organization" />

                        <ul className="divide-y rounded-lg border">
                            {members.map((member) => (
                                <li key={member.id} className="flex flex-wrap items-center justify-between gap-3 p-4">
                                    <div className="min-w-0">
                                        <p className="truncate font-medium">
                                            {member.name}
                                            {member.status === 'deactivated' && (
                                                <Badge variant="outline" className="ml-2">
                                                    Deactivated
                                                </Badge>
                                            )}
                                        </p>
                                        <p className="text-muted-foreground truncate text-sm">{member.email}</p>
                                    </div>

                                    <div className="flex items-center gap-2">
                                        {can('members.update') ? (
                                            <Select
                                                value={member.role}
                                                onValueChange={(role) =>
                                                    router.patch(route('members.role', member.id), { role }, { preserveScroll: true })
                                                }
                                            >
                                                <SelectTrigger className="w-44">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {assignableRoles.map((r) => (
                                                        <SelectItem key={r.value} value={r.value}>
                                                            {r.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        ) : (
                                            <Badge variant="secondary">{roleLabel(member.role)}</Badge>
                                        )}

                                        {can('members.update') &&
                                            (member.status === 'active' ? (
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() => router.patch(route('members.deactivate', member.id), {}, { preserveScroll: true })}
                                                >
                                                    Deactivate
                                                </Button>
                                            ) : (
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() => router.patch(route('members.reactivate', member.id), {}, { preserveScroll: true })}
                                                >
                                                    Reactivate
                                                </Button>
                                            ))}

                                        {can('members.remove') && (
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="text-red-600"
                                                onClick={() => router.delete(route('members.destroy', member.id), { preserveScroll: true })}
                                            >
                                                Remove
                                            </Button>
                                        )}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </div>

                    {can('members.invite') && (
                        <div className="space-y-4">
                            <HeadingSmall title="Invite a member" description="They'll receive an email to join this organization" />

                            <form onSubmit={submitInvite} className="flex flex-wrap items-end gap-3">
                                <div className="grid flex-1 gap-2" style={{ minWidth: '16rem' }}>
                                    <Label htmlFor="email">Email address</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        value={invite.data.email}
                                        onChange={(e) => invite.setData('email', e.target.value)}
                                        placeholder="teammate@example.com"
                                    />
                                    <InputError message={invite.errors.email} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="role">Role</Label>
                                    <Select value={invite.data.role} onValueChange={(role) => invite.setData('role', role)}>
                                        <SelectTrigger id="role" className="w-44">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {assignableRoles.map((r) => (
                                                <SelectItem key={r.value} value={r.value}>
                                                    {r.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <Button disabled={invite.processing}>Send invite</Button>
                            </form>

                            {invitations.length > 0 && (
                                <div className="space-y-2">
                                    <p className="text-muted-foreground text-sm">Pending invitations</p>
                                    <ul className="divide-y rounded-lg border">
                                        {invitations.map((inv) => (
                                            <li key={inv.id} className="flex items-center justify-between gap-3 p-3">
                                                <div className="min-w-0">
                                                    <p className="truncate text-sm font-medium">{inv.email}</p>
                                                    <p className="text-muted-foreground text-xs">{roleLabel(inv.role)}</p>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() => router.post(route('invitations.resend', inv.id), {}, { preserveScroll: true })}
                                                    >
                                                        Resend
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        className="text-red-600"
                                                        onClick={() => router.delete(route('invitations.destroy', inv.id), { preserveScroll: true })}
                                                    >
                                                        Revoke
                                                    </Button>
                                                </div>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}
                        </div>
                    )}
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
