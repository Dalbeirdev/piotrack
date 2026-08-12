import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';

/**
 * Permission-aware visibility for the UI (RBAC-005). This gates what the user
 * SEES; the backend Gate is the security boundary. The permission list is the
 * set the user holds in the current organization.
 */
export function usePermissions() {
    const { auth } = usePage<SharedData>().props;
    const permissions = auth?.permissions ?? [];

    const can = (permission: string): boolean => permissions.includes(permission);
    const canAny = (...checks: string[]): boolean => checks.some((p) => permissions.includes(p));

    return { permissions, can, canAny, role: auth?.role ?? null };
}
