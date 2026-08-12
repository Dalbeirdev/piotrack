import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Teams', href: '/settings/teams' }];

type OrgMember = { id: number; name: string; email: string };
type Team = { id: number; name: string; members: OrgMember[] };

type TeamsProps = {
    teams: Team[];
    organizationMembers: OrgMember[];
};

export default function Teams({ teams, organizationMembers }: TeamsProps) {
    const { can } = usePermissions();
    const create = useForm({ name: '' });
    const [addSelection, setAddSelection] = useState<Record<number, string>>({});

    const submitCreate: FormEventHandler = (e) => {
        e.preventDefault();
        create.post(route('teams.store'), { preserveScroll: true, onSuccess: () => create.reset() });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Teams" />

            <SettingsLayout>
                <div className="space-y-8">
                    <HeadingSmall title="Teams" description="Group members within this organization" />

                    {teams.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No teams yet. Create one below to group your members.</p>
                    ) : (
                        <ul className="space-y-4">
                            {teams.map((team) => (
                                <li key={team.id} className="space-y-3 rounded-lg border p-4">
                                    <div className="flex items-center justify-between">
                                        <p className="font-medium">{team.name}</p>
                                        {can('teams.manage') && (
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="text-red-600"
                                                onClick={() => router.delete(route('teams.destroy', team.id), { preserveScroll: true })}
                                            >
                                                Delete team
                                            </Button>
                                        )}
                                    </div>

                                    {team.members.length > 0 ? (
                                        <ul className="flex flex-wrap gap-2">
                                            {team.members.map((m) => (
                                                <li key={m.id} className="bg-muted flex items-center gap-2 rounded-full py-1 pr-1 pl-3 text-sm">
                                                    <span>{m.name}</span>
                                                    {can('teams.manage') && (
                                                        <button
                                                            type="button"
                                                            aria-label={`Remove ${m.name}`}
                                                            className="text-muted-foreground hover:text-red-600"
                                                            onClick={() =>
                                                                router.delete(route('teams.members.remove', [team.id, m.id]), {
                                                                    preserveScroll: true,
                                                                })
                                                            }
                                                        >
                                                            ×
                                                        </button>
                                                    )}
                                                </li>
                                            ))}
                                        </ul>
                                    ) : (
                                        <p className="text-muted-foreground text-sm">No members in this team yet.</p>
                                    )}

                                    {can('teams.manage') && organizationMembers.length > 0 && (
                                        <div className="flex items-end gap-2">
                                            <Select
                                                value={addSelection[team.id] ?? ''}
                                                onValueChange={(v) => setAddSelection((s) => ({ ...s, [team.id]: v }))}
                                            >
                                                <SelectTrigger className="w-56">
                                                    <SelectValue placeholder="Add a member…" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {organizationMembers
                                                        .filter((m) => !team.members.some((tm) => tm.id === m.id))
                                                        .map((m) => (
                                                            <SelectItem key={m.id} value={String(m.id)}>
                                                                {m.name}
                                                            </SelectItem>
                                                        ))}
                                                </SelectContent>
                                            </Select>
                                            <Button
                                                size="sm"
                                                disabled={!addSelection[team.id]}
                                                onClick={() =>
                                                    router.post(
                                                        route('teams.members.add', team.id),
                                                        { user_id: addSelection[team.id] },
                                                        {
                                                            preserveScroll: true,
                                                            onSuccess: () => setAddSelection((s) => ({ ...s, [team.id]: '' })),
                                                        },
                                                    )
                                                }
                                            >
                                                Add
                                            </Button>
                                        </div>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}

                    {can('teams.manage') && (
                        <form onSubmit={submitCreate} className="flex items-end gap-3">
                            <div className="grid flex-1 gap-2" style={{ maxWidth: '20rem' }}>
                                <Label htmlFor="team_name">New team name</Label>
                                <Input
                                    id="team_name"
                                    value={create.data.name}
                                    onChange={(e) => create.setData('name', e.target.value)}
                                    placeholder="e.g. Onboarding pod"
                                />
                                <InputError message={create.errors.name} />
                            </div>
                            <Button disabled={create.processing}>Create team</Button>
                        </form>
                    )}
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
