import { Alert, AlertDescription } from '@/components/ui/alert';
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { CheckCircle2, X } from 'lucide-react';
import { useEffect, useState } from 'react';

/**
 * Renders the session `status` flash so confirmations from the server are
 * actually visible. Resets whenever a new flash arrives.
 */
export function FlashMessage() {
    const { flash } = usePage<SharedData>().props;
    const status = flash?.status ?? null;
    const [dismissed, setDismissed] = useState(false);

    useEffect(() => {
        setDismissed(false);
    }, [status]);

    if (!status || dismissed) {
        return null;
    }

    return (
        <div className="px-4 pt-4">
            <Alert className="flex items-start gap-2">
                <CheckCircle2 className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                <AlertDescription className="flex-1">{status}</AlertDescription>
                <button
                    type="button"
                    onClick={() => setDismissed(true)}
                    aria-label="Dismiss message"
                    className="text-muted-foreground hover:text-foreground shrink-0"
                >
                    <X className="size-4" />
                </button>
            </Alert>
        </div>
    );
}
