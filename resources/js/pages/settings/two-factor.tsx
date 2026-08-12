import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { QRCodeSVG } from 'qrcode.react';
import { FormEventHandler } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Two-factor authentication',
        href: '/settings/two-factor',
    },
];

type TwoFactorProps = {
    enabled: boolean;
    pendingSecret: string | null;
    provisioningUri: string | null;
    recoveryCodes: string[];
};

export default function TwoFactor({ enabled, pendingSecret, provisioningUri, recoveryCodes }: TwoFactorProps) {
    const enable = useForm({});
    const disable = useForm({});
    const regenerate = useForm({});
    const confirm = useForm({ code: '' });

    const submitConfirm: FormEventHandler = (e) => {
        e.preventDefault();

        confirm.post(route('two-factor.confirm'), {
            preserveScroll: true,
            onError: () => confirm.reset('code'),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Two-factor authentication" />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall
                        title="Two-factor authentication"
                        description="Require a code from an authenticator app in addition to your password when signing in"
                    />

                    {!enabled && !pendingSecret && (
                        <div className="space-y-4">
                            <p className="text-muted-foreground text-sm">
                                Two-factor authentication is currently <Badge variant="outline">disabled</Badge>. When enabled, you will be asked for
                                a secure code from an authenticator app (such as Google Authenticator or 1Password) each time you sign in.
                            </p>

                            <Button onClick={() => enable.post(route('two-factor.enable'), { preserveScroll: true })} disabled={enable.processing}>
                                Enable two-factor authentication
                            </Button>
                        </div>
                    )}

                    {!enabled && pendingSecret && provisioningUri && (
                        <div className="space-y-4">
                            <p className="text-muted-foreground text-sm">
                                Scan the QR code below with your authenticator app, then enter the six-digit code it generates to finish enrollment.
                            </p>

                            <div className="w-fit rounded-lg border bg-white p-4">
                                <QRCodeSVG value={provisioningUri} size={192} />
                            </div>

                            <p className="text-muted-foreground text-sm">
                                Can't scan? Enter this key manually: <code className="bg-muted rounded px-1 py-0.5 font-mono">{pendingSecret}</code>
                            </p>

                            <form onSubmit={submitConfirm} className="space-y-4">
                                <div className="grid max-w-xs gap-2">
                                    <Label htmlFor="code">Authenticator code</Label>
                                    <Input
                                        id="code"
                                        value={confirm.data.code}
                                        onChange={(e) => confirm.setData('code', e.target.value)}
                                        autoComplete="one-time-code"
                                        inputMode="numeric"
                                        placeholder="123456"
                                    />
                                    <InputError message={confirm.errors.code} />
                                </div>

                                <div className="flex items-center gap-3">
                                    <Button disabled={confirm.processing}>Confirm & activate</Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        onClick={() => disable.delete(route('two-factor.disable'), { preserveScroll: true })}
                                        disabled={disable.processing}
                                    >
                                        Cancel
                                    </Button>
                                </div>
                            </form>
                        </div>
                    )}

                    {enabled && (
                        <div className="space-y-6">
                            <p className="text-muted-foreground text-sm">
                                Two-factor authentication is <Badge>enabled</Badge>. You will be asked for an authenticator code when signing in.
                            </p>

                            <div className="space-y-3">
                                <HeadingSmall
                                    title="Recovery codes"
                                    description="Each code can be used once to sign in if you lose access to your authenticator app. Store them somewhere safe."
                                />

                                <div className="bg-muted/40 grid max-w-sm grid-cols-2 gap-1 rounded-lg border p-4 font-mono text-sm">
                                    {recoveryCodes.map((code) => (
                                        <div key={code}>{code}</div>
                                    ))}
                                </div>

                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => regenerate.post(route('two-factor.recovery-codes'), { preserveScroll: true })}
                                    disabled={regenerate.processing}
                                >
                                    Regenerate recovery codes
                                </Button>
                            </div>

                            <div className="space-y-3">
                                <HeadingSmall
                                    title="Disable two-factor authentication"
                                    description="Your account will no longer require a second factor at sign-in"
                                />
                                <Button
                                    variant="destructive"
                                    onClick={() => disable.delete(route('two-factor.disable'), { preserveScroll: true })}
                                    disabled={disable.processing}
                                >
                                    Disable two-factor authentication
                                </Button>
                            </div>
                        </div>
                    )}
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
