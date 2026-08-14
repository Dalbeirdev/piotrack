<?php

namespace App\Services\Web;

use App\Models\Contact;
use App\Models\Deal;
use App\Models\SeoLocation;
use App\Models\SitePage;
use App\Support\AuditLogger;
use Illuminate\Support\Collection;

/**
 * Multi-location support (MLOC). Extends the Stage 7 NAP location record with a
 * sales territory, its own Google Business Profile mapping and a location page,
 * then rolls results up per branch.
 *
 * Location-level attribution (MLOC-012) reuses the real CRM data: contacts and
 * deals are attributed to a branch by matching the contact's city to the
 * territory or city of the location, so a branch's numbers come from records
 * rather than a manual tally.
 */
class LocationService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): SeoLocation
    {
        $location = SeoLocation::create($data + ['is_active' => true]);

        $this->audit->log('web.location.created', context: ['name' => $location->name, 'city' => $location->city],
            resourceType: 'seo_location', resourceId: (string) $location->id);

        return $location;
    }

    /**
     * Per-branch rollup: its page, NAP consistency inputs and attributed results.
     *
     * @return list<array<string, mixed>>
     */
    public function report(): array
    {
        return SeoLocation::orderBy('name')->get()->map(function (SeoLocation $location) {
            $contacts = $this->attributedContacts($location);
            $contactIds = $contacts->pluck('id')->all();

            $wonValue = $contactIds === [] ? 0 : (int) Deal::whereIn('contact_id', $contactIds)
                ->whereHas('stage', fn ($q) => $q->where('is_won', true))->sum('value');

            return [
                'id' => $location->id,
                'name' => $location->name,
                'city' => $location->city,
                'region' => $location->region,
                'territory' => $location->territory,
                'is_active' => (bool) $location->is_active,
                'has_page' => SitePage::where('seo_location_id', $location->id)->exists(),
                'published_page' => SitePage::where('seo_location_id', $location->id)
                    ->where('status', SitePage::STATUS_PUBLISHED)->exists(),
                'leads' => $contacts->count(),
                'sqls' => $contacts->where('lifecycle_stage', 'sql')->count(),
                'won_value' => $wonValue,
            ];
        })->all();
    }

    /**
     * Contacts attributed to a branch, through their company's city or region —
     * contacts carry no address of their own, the company does.
     *
     * A contact with no company, or whose company has no matching city/region,
     * is deliberately left unattributed rather than assigned to the nearest
     * branch: a confident wrong attribution is worse than a visible gap.
     *
     * @return Collection<int, Contact>
     */
    public function attributedContacts(SeoLocation $location): Collection
    {
        $needles = array_values(array_filter([$location->territory, $location->city, $location->region]));

        if ($needles === []) {
            return collect();
        }

        return Contact::query()
            ->whereHas('company', function ($query) use ($needles) {
                $query->where(function ($inner) use ($needles) {
                    foreach ($needles as $needle) {
                        $inner->orWhere('city', $needle)->orWhere('region', $needle);
                    }
                });
            })
            ->get();
    }
}
