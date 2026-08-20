import { cn } from '@/lib/utils';
import { type LucideIcon, TrendingDown, TrendingUp } from 'lucide-react';

/**
 * A single KPI tile (audit §18).
 *
 * Dashboards answer "what happened" with a row of these: a label, the value,
 * and an optional period-over-period delta whose colour carries meaning
 * (green up, red down) rather than decoration. Formalises the ad-hoc Card + Badge
 * compositions the dashboards each built by hand.
 */
export function StatCard({
    label,
    value,
    delta,
    icon: Icon,
    className,
}: {
    label: string;
    value: string | number;
    delta?: { value: string; direction: 'up' | 'down' | 'neutral' };
    icon?: LucideIcon;
    className?: string;
}) {
    const DeltaIcon = delta?.direction === 'down' ? TrendingDown : TrendingUp;

    return (
        <div className={cn('border-border bg-card rounded-lg border p-4', className)}>
            <div className="flex items-center justify-between gap-2">
                <span className="text-muted-foreground text-sm font-medium">{label}</span>
                {Icon && <Icon className="text-muted-foreground size-4" aria-hidden />}
            </div>
            <div className="mt-2 flex items-baseline gap-2">
                <span className="text-foreground text-2xl font-semibold tracking-tight tabular-nums">{value}</span>
                {delta && (
                    <span
                        className={cn(
                            'inline-flex items-center gap-0.5 text-xs font-medium',
                            delta.direction === 'up' && 'text-emerald-600 dark:text-emerald-400',
                            delta.direction === 'down' && 'text-red-600 dark:text-red-400',
                            delta.direction === 'neutral' && 'text-muted-foreground',
                        )}
                    >
                        {delta.direction !== 'neutral' && <DeltaIcon className="size-3" aria-hidden />}
                        {delta.value}
                    </span>
                )}
            </div>
        </div>
    );
}
