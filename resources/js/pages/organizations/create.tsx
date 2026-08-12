import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';
import { type SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';

type CreateOrganizationForm = {
    name: string;
};

export default function CreateOrganization() {
    const { auth } = usePage<SharedData>().props;
    const hasOrganizations = (auth.organizations ?? []).length > 0;

    const { data, setData, post, processing, errors } = useForm<CreateOrganizationForm>({ name: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('organizations.store'));
    };

    return (
        <AuthLayout
            title={hasOrganizations ? 'Create an organization' : 'Create your organization'}
            description="An organization is your workspace — its members, data, and settings live inside it."
        >
            <Head title="Create organization" />

            <form className="flex flex-col gap-6" onSubmit={submit}>
                <div className="grid gap-2">
                    <Label htmlFor="name">Organization name</Label>
                    <Input
                        id="name"
                        autoFocus
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        placeholder="Acme Managed Services"
                    />
                    <InputError message={errors.name} />
                </div>

                <Button type="submit" disabled={processing}>
                    Create organization
                </Button>
            </form>
        </AuthLayout>
    );
}
