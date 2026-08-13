import HeadingSmall from '@/components/heading-small';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { formatMoney, humanizeKey } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Plans', href: '/billing/plans' }];

type Plan = {
    code: string;
    name: string;
    description: string | null;
    is_custom_priced: boolean;
    prices: { monthly: number | null; annual: number | null };
    features: string[];
    limits: Record<string, number | null>;
};

type PlansProps = {
    plans: Plan[];
    currentPlan: string | null;
};

export default function Plans({ plans, currentPlan }: PlansProps) {
    const [interval, setInterval] = useState<'monthly' | 'annual'>('monthly');

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Plans" />

            <SettingsLayout>
                <div className="space-y-6">
                    <div className="flex items-center justify-between">
                        <HeadingSmall title="Plans" description="Choose the plan that fits your team" />
                        <div className="inline-flex rounded-lg border p-0.5 text-sm">
                            <button
                                className={`rounded-md px-3 py-1 ${interval === 'monthly' ? 'bg-muted font-medium' : 'text-muted-foreground'}`}
                                onClick={() => setInterval('monthly')}
                            >
                                Monthly
                            </button>
                            <button
                                className={`rounded-md px-3 py-1 ${interval === 'annual' ? 'bg-muted font-medium' : 'text-muted-foreground'}`}
                                onClick={() => setInterval('annual')}
                            >
                                Annual
                            </button>
                        </div>
                    </div>

                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {plans.map((plan) => {
                            const price = plan.prices[interval];
                            const isCurrent = plan.code === currentPlan;

                            return (
                                <Card key={plan.code} className={isCurrent ? 'border-primary' : ''}>
                                    <CardHeader>
                                        <div className="flex items-center justify-between">
                                            <CardTitle>{plan.name}</CardTitle>
                                            {isCurrent && <Badge>Current</Badge>}
                                        </div>
                                        <CardDescription>{plan.description}</CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <div>
                                            {plan.is_custom_priced ? (
                                                <p className="text-2xl font-semibold">Custom</p>
                                            ) : (
                                                <p className="text-2xl font-semibold">
                                                    {formatMoney(price)}
                                                    <span className="text-muted-foreground text-sm font-normal">
                                                        /{interval === 'monthly' ? 'mo' : 'yr'}
                                                    </span>
                                                </p>
                                            )}
                                        </div>

                                        <ul className="space-y-1 text-sm">
                                            <li className="flex items-center gap-2">
                                                <Check className="text-primary size-4" />
                                                {plan.limits.members == null ? 'Unlimited' : plan.limits.members} members
                                            </li>
                                            {plan.features.map((f) => (
                                                <li key={f} className="flex items-center gap-2">
                                                    <Check className="text-primary size-4" />
                                                    {humanizeKey(f)}
                                                </li>
                                            ))}
                                        </ul>

                                        {plan.is_custom_priced ? (
                                            <Button variant="outline" className="w-full" asChild>
                                                <a href="mailto:sales@piotrack.com">Contact sales</a>
                                            </Button>
                                        ) : (
                                            <Button
                                                className="w-full"
                                                variant={isCurrent ? 'outline' : 'default'}
                                                disabled={isCurrent}
                                                onClick={() => router.get(route('billing.checkout.show'), { plan: plan.code, interval })}
                                            >
                                                {isCurrent ? 'Current plan' : 'Choose plan'}
                                            </Button>
                                        )}
                                    </CardContent>
                                </Card>
                            );
                        })}
                    </div>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
