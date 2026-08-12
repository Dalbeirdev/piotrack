<?php

namespace App\Http\Middleware;

use App\Support\CurrentOrganization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the tenant the authenticated user is acting within (TEN-005) and
 * binds it into the request-scoped CurrentOrganization service, which drives
 * tenant isolation, the RBAC gate, and audit organization stamping.
 *
 * Source of truth is users.current_organization_id, validated against active
 * membership. If it is missing or stale, the user's first active organization
 * is selected and persisted.
 */
class SetCurrentOrganization
{
    public function __construct(private CurrentOrganization $currentOrganization) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null) {
            $organization = $user->current_organization_id !== null
                ? $user->activeOrganizations()->where('organizations.id', $user->current_organization_id)->first()
                : null;

            if ($organization === null) {
                $organization = $user->activeOrganizations()->first();

                // Persist the resolved organization so it sticks across requests.
                if ($organization !== null && $user->current_organization_id !== $organization->id) {
                    $user->forceFill(['current_organization_id' => $organization->id])->save();
                } elseif ($organization === null && $user->current_organization_id !== null) {
                    $user->forceFill(['current_organization_id' => null])->save();
                }
            }

            $this->currentOrganization->set($organization);
        }

        return $next($request);
    }
}
