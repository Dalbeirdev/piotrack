<?php

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use App\Models\Citation;
use App\Models\SeoLocation;
use App\Services\Seo\NapConsistencyChecker;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LocalController extends Controller
{
    public function __construct(
        private NapConsistencyChecker $nap,
        private AuditLogger $audit,
    ) {}

    public function index(): Response
    {
        return Inertia::render('seo/local/index', [
            'locations' => SeoLocation::with('citations')->latest('id')->get()->map(fn (SeoLocation $l) => [
                'id' => $l->id,
                'name' => $l->name,
                'address' => trim(implode(', ', array_filter([$l->street, $l->city, $l->region, $l->postal_code]))),
                'phone' => $l->phone,
                'website' => $l->website,
                'citations' => $l->citations->map(fn (Citation $c) => [
                    'id' => $c->id,
                    'source' => $c->source,
                    'status' => $c->status,
                    'mismatches' => $c->mismatches ?? [],
                    'listed_name' => $c->listed_name,
                    'listed_address' => $c->listed_address,
                    'listed_phone' => $c->listed_phone,
                ])->all(),
            ]),
        ]);
    }

    public function storeLocation(Request $request): RedirectResponse
    {
        $location = SeoLocation::create($request->validate([
            'name' => ['required', 'string', 'max:200'],
            'street' => ['nullable', 'string', 'max:200'],
            'city' => ['nullable', 'string', 'max:120'],
            'region' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:80'],
            'phone' => ['nullable', 'string', 'max:40'],
            'website' => ['nullable', 'url', 'max:255'],
        ]));

        $this->audit->log('seo.location.created', context: ['name' => $location->name], resourceType: 'seo_location', resourceId: (string) $location->id, organizationId: $location->organization_id);

        return back()->with('status', __('Location added.'));
    }

    public function destroyLocation(SeoLocation $location): RedirectResponse
    {
        $location->delete();

        return back()->with('status', __('Location removed.'));
    }

    public function storeCitation(Request $request, SeoLocation $location): RedirectResponse
    {
        $data = $request->validate([
            'source' => ['required', 'string', 'max:120'],
            'listed_name' => ['nullable', 'string', 'max:200'],
            'listed_address' => ['nullable', 'string', 'max:255'],
            'listed_phone' => ['nullable', 'string', 'max:40'],
            'url' => ['nullable', 'url', 'max:255'],
        ]);

        $result = $this->nap->check($location, [
            'name' => $data['listed_name'] ?? null,
            'address' => $data['listed_address'] ?? null,
            'phone' => $data['listed_phone'] ?? null,
        ]);

        $location->citations()->create([
            ...$data,
            'status' => $result['status'],
            'mismatches' => $result['mismatches'],
            'checked_at' => now(),
        ]);

        $this->audit->log('seo.citation.checked', context: ['source' => $data['source'], 'status' => $result['status']], resourceType: 'seo_location', resourceId: (string) $location->id, organizationId: $location->organization_id);

        return back()->with('status', __('Citation :status.', ['status' => $result['status']]));
    }

    public function checkCitation(SeoLocation $location, Citation $citation): RedirectResponse
    {
        $result = $this->nap->check($location, [
            'name' => $citation->listed_name,
            'address' => $citation->listed_address,
            'phone' => $citation->listed_phone,
        ]);

        $citation->update(['status' => $result['status'], 'mismatches' => $result['mismatches'], 'checked_at' => now()]);

        return back()->with('status', __('Citation re-checked: :status.', ['status' => $result['status']]));
    }

    public function destroyCitation(SeoLocation $location, Citation $citation): RedirectResponse
    {
        $citation->delete();

        return back()->with('status', __('Citation removed.'));
    }
}
