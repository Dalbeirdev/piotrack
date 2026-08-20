import { cn } from '@/lib/utils';
import { type LucideIcon } from 'lucide-react';

/**
 * A professional empty state (audit §28).
 *
 * Replaces the bare "No data" line with a restrained, centred block: an icon, a
 * short title, one sentence on what to do, and the primary action inline. No
 * heavy illustrations. Used wherever a list, table or dashboard can legitimately
 * hold nothing yet.
 */
export function EmptyState({
    icon: Icon,
    title,
    description,
    action,
    className,
}: {
    icon?: LucideIcon;
    title: string;
    description?: string;
    action?: React.ReactNode;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'border-border flex flex-col items-center justify-center rounded-lg border border-dashed px-6 py-12 text-center',
                className,
            )}
        >
            {Icon && (
                <div className="bg-muted mb-3 flex size-11 items-center justify-center rounded-full">
                    <Icon className="text-muted-foreground size-5" aria-hidden />
                </div>
            )}
            <h3 className="text-foreground text-sm font-semibold">{title}</h3>
            {description && <p className="text-muted-foreground mt-1 max-w-sm text-sm">{description}</p>}
            {action && <div className="mt-4">{action}</div>}
        </div>
    );
}
