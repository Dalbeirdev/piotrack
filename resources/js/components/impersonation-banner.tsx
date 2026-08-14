import { Button } from '@/components/ui/button';
import { type SharedData } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { ShieldAlert } from 'lucide-react';

/**
 * The impersonation banner (ADMIN-005). While a support session is active every
 * page in the product is being viewed through somebody else's account, so this
 * is deliberately impossible to miss: full width, sticky, destructive colours,
 * and it names both people involved. It cannot be dismissed — the only way to
 * clear it is to stop impersonating.
 */
export function ImpersonationBanner() {
    const { impersonation } = usePage<SharedData>().props;

    if (!impersonation?.active) {
        return null;
    }

    const impersonator = impersonation.impersonator ?? 'A platform administrator';
    const user = impersonation.user ?? 'another user';

    const stop = () => router.post(route('impersonate.stop'));

    return (
        <div
            role="alert"
            aria-live="assertive"
            className="bg-destructive text-destructive-foreground sticky top-0 z-50 w-full border-b border-white/20 shadow-lg md:rounded-t-xl"
        >
            <div className="flex flex-wrap items-center gap-3 px-4 py-3">
                <ShieldAlert className="size-5 shrink-0" aria-hidden="true" />
                <div className="min-w-0 flex-1">
                    <p className="text-sm font-semibold">
                        Impersonation active — {impersonator} is acting as {user}.
                    </p>
                    <p className="text-xs opacity-90">
                        Everything on this screen is {user}&rsquo;s data, and every action taken here is recorded against this support session and
                        visible to them. Stop as soon as you are done.
                    </p>
                </div>
                <Button
                    type="button"
                    onClick={stop}
                    variant="secondary"
                    className="shrink-0 font-semibold shadow-sm"
                    aria-label={`Stop impersonating ${user}`}
                >
                    Stop impersonating
                </Button>
            </div>
        </div>
    );
}
