import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { formatMoney } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Plans', href: '/billing/plans' },
    { title: 'Checkout', href: '/billing/checkout' },
];

type CheckoutProps = {
    plan: {
        code: string;
        name: string;
        prices: { monthly: number | null; annual: number | null };
    };
    interval: 'monthly' | 'annual';
};

export default function Checkout({ plan, interval }: CheckoutProps) {
    const price = plan.prices[interval] ?? 0;

    const { data, setData, post, processing, errors } = useForm({
        plan: plan.code,
        interval,
        quantity: 1,
        coupon: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('billing.checkout.store'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Checkout" />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall title="Checkout" description={`Subscribe to ${plan.name} (${interval})`} />

                    <Card>
                        <CardHeader>
                            <CardTitle>Order summary</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex items-center justify-between text-sm">
                                <span>
                                    {plan.name} — {interval}
                                </span>
                                <span className="font-medium">
                                    {formatMoney(price)}/{interval === 'monthly' ? 'mo' : 'yr'}
                                </span>
                            </div>

                            <form onSubmit={submit} className="space-y-4">
                                <div className="grid max-w-xs gap-2">
                                    <Label htmlFor="coupon">Promo code (optional)</Label>
                                    <Input
                                        id="coupon"
                                        value={data.coupon}
                                        onChange={(e) => setData('coupon', e.target.value)}
                                        placeholder="e.g. LAUNCH20"
                                    />
                                    <InputError message={errors.coupon} />
                                </div>

                                <Button disabled={processing}>Confirm &amp; subscribe</Button>
                                <p className="text-muted-foreground text-xs">
                                    This environment uses the manual billing provider — no card is charged. Configure Stripe to collect payment.
                                </p>
                            </form>
                        </CardContent>
                    </Card>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
