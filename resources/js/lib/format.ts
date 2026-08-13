/**
 * Format a minor-unit amount (cents) as a currency string.
 */
export function formatMoney(amount: number | null | undefined, currency = 'USD'): string {
    if (amount === null || amount === undefined) {
        return '—';
    }

    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency,
        minimumFractionDigits: amount % 100 === 0 ? 0 : 2,
    }).format(amount / 100);
}

/**
 * Human label for an entitlement/limit key.
 */
export function humanizeKey(key: string): string {
    return key.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}
