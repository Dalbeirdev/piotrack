<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\OrganizationService;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationSettingsController extends Controller
{
    public function __construct(
        private OrganizationService $organizations,
        private CurrentOrganization $currentOrganization,
    ) {}

    public function edit(): Response
    {
        $organization = $this->currentOrganization->get();

        return Inertia::render('settings/organization', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $this->organizations->update($this->currentOrganization->get(), $validated['name']);

        return back();
    }

    public function destroy(Request $request): RedirectResponse
    {
        $organization = $this->currentOrganization->get();

        $request->validate([
            'name' => ['required', Rule::in([$organization->name])],
        ], [
            'name.in' => __('Please type the organization name exactly to confirm deletion.'),
        ]);

        $this->organizations->delete($organization);

        return redirect()->route('dashboard');
    }
}
