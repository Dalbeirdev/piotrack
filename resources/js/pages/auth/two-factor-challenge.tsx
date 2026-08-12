import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

type ChallengeForm = {
    code: string;
    recovery_code: string;
};

export default function TwoFactorChallenge() {
    const [usingRecoveryCode, setUsingRecoveryCode] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm<ChallengeForm>({
        code: '',
        recovery_code: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('two-factor.challenge'), {
            onError: () => reset('code', 'recovery_code'),
        });
    };

    return (
        <AuthLayout
            title="Two-factor authentication"
            description={
                usingRecoveryCode ? 'Enter one of your recovery codes to sign in' : 'Enter the code from your authenticator app to finish signing in'
            }
        >
            <Head title="Two-factor authentication" />

            <form className="flex flex-col gap-6" onSubmit={submit}>
                <div className="grid gap-6">
                    {!usingRecoveryCode ? (
                        <div className="grid gap-2">
                            <Label htmlFor="code">Authenticator code</Label>
                            <Input
                                id="code"
                                type="text"
                                required
                                autoFocus
                                inputMode="numeric"
                                autoComplete="one-time-code"
                                value={data.code}
                                onChange={(e) => setData('code', e.target.value)}
                                placeholder="123456"
                            />
                            <InputError message={errors.code} />
                        </div>
                    ) : (
                        <div className="grid gap-2">
                            <Label htmlFor="recovery_code">Recovery code</Label>
                            <Input
                                id="recovery_code"
                                type="text"
                                required
                                autoFocus
                                autoComplete="off"
                                value={data.recovery_code}
                                onChange={(e) => setData('recovery_code', e.target.value)}
                                placeholder="XXXXX-XXXXX"
                            />
                            <InputError message={errors.recovery_code} />
                        </div>
                    )}

                    <Button type="submit" className="mt-2 w-full" disabled={processing}>
                        Verify
                    </Button>
                </div>

                <div className="text-muted-foreground text-center text-sm">
                    <button
                        type="button"
                        className="hover:text-foreground underline underline-offset-4"
                        onClick={() => {
                            setUsingRecoveryCode(!usingRecoveryCode);
                            reset('code', 'recovery_code');
                        }}
                    >
                        {usingRecoveryCode ? 'Use an authenticator code instead' : 'Use a recovery code instead'}
                    </button>
                    <span className="mx-2">·</span>
                    <TextLink href={route('login')}>Back to login</TextLink>
                </div>
            </form>
        </AuthLayout>
    );
}
