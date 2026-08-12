<?php

namespace App\Http\Middleware;

use App\Support\CurrentOrganization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards tenant-scoped surfaces: a verified user with no organization is sent
 * to create their first one (the onboarding entry point). Applied after
 * SetCurrentOrganization has resolved the tenant.
 */
class EnsureHasOrganization
{
    public function __construct(private CurrentOrganization $currentOrganization) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() !== null && ! $this->currentOrganization->isSet()) {
            return redirect()->route('organizations.create');
        }

        return $next($request);
    }
}
