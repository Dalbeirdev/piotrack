import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Link } from '@inertiajs/react';
import { Check, Circle } from 'lucide-react';
import { useState } from 'react';

type Step = { key: string; label: string; done: boolean; url: string };
type Onboarding = { steps: Step[]; complete: boolean };

const DISMISS_KEY = 'piotrack:onboarding-dismissed';

/**
 * Setup checklist (ONBD-013). Progress is derived from real state (resumable),
 * hides itself when complete, and can be dismissed client-side.
 */
export function OnboardingChecklist({ onboarding }: { onboarding: Onboarding }) {
    const [dismissed, setDismissed] = useState(() => localStorage.getItem(DISMISS_KEY) === '1');

    if (onboarding.complete || dismissed) {
        return null;
    }

    const done = onboarding.steps.filter((s) => s.done).length;
    const total = onboarding.steps.length;

    const dismiss = () => {
        localStorage.setItem(DISMISS_KEY, '1');
        setDismissed(true);
    };

    return (
        <Card>
            <CardHeader>
                <div className="flex items-center justify-between">
                    <div>
                        <CardTitle>Finish setting up</CardTitle>
                        <CardDescription>
                            {done} of {total} steps complete
                        </CardDescription>
                    </div>
                    <button onClick={dismiss} className="text-muted-foreground hover:text-foreground text-sm">
                        Dismiss
                    </button>
                </div>
            </CardHeader>
            <CardContent>
                <ul className="space-y-1">
                    {onboarding.steps.map((step) => (
                        <li key={step.key}>
                            <Link href={step.url} className="hover:bg-muted flex items-center gap-3 rounded-md px-2 py-1.5 text-sm">
                                {step.done ? <Check className="size-4 text-green-600" /> : <Circle className="text-muted-foreground size-4" />}
                                <span className={step.done ? 'text-muted-foreground line-through' : ''}>{step.label}</span>
                            </Link>
                        </li>
                    ))}
                </ul>
            </CardContent>
        </Card>
    );
}
