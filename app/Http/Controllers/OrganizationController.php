<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Services\OrganizationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Organization lifecycle that sits outside a tenant context: creating an
 * organization (first-run onboarding and additional orgs) and switching the
 * active one. Deliberately NOT behind the `organization` guard.
 */
class OrganizationController extends Controller
{
    public function __construct(private OrganizationService $organizations) {}

    public function create(): Response
    {
        return Inertia::render('organizations/create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $this->organizations->create($request->user(), $validated['name']);

        return redirect()->route('dashboard');
    }

    public function switch(Request $request, Organization $organization): RedirectResponse
    {
        $this->organizations->switchTo($request->user(), $organization);

        return back();
    }
}
