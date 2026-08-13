<?php

namespace App\Services\Marketing;

use App\Models\Contact;
use App\Models\ListMembership;
use App\Models\MarketingList;
use Illuminate\Database\Eloquent\Collection;

/**
 * Lists / segments (LEAD-017). Static lists hold explicit memberships; dynamic
 * lists resolve members from stored criteria at query time.
 */
class ListService
{
    public function addContact(MarketingList $list, Contact $contact): void
    {
        ListMembership::firstOrCreate(
            ['marketing_list_id' => $list->id, 'contact_id' => $contact->id],
            ['organization_id' => $list->organization_id, 'added_at' => now()],
        );

        $this->recount($list);
    }

    public function removeContact(MarketingList $list, Contact $contact): void
    {
        ListMembership::where('marketing_list_id', $list->id)
            ->where('contact_id', $contact->id)
            ->delete();

        $this->recount($list);
    }

    /**
     * Resolve the contacts a list currently targets. Static lists use explicit
     * memberships; dynamic lists apply their criteria (lifecycle_stage,
     * min_lead_score, lead_source).
     *
     * @return Collection<int, Contact>
     */
    public function members(MarketingList $list): Collection
    {
        if ($list->type !== 'dynamic') {
            return $list->contacts()->get();
        }

        $criteria = $list->criteria ?? [];

        return Contact::query()
            ->when($criteria['lifecycle_stage'] ?? null, fn ($q, $s) => $q->where('lifecycle_stage', $s))
            ->when($criteria['lead_source'] ?? null, fn ($q, $s) => $q->where('lead_source', $s))
            ->when($criteria['min_lead_score'] ?? null, fn ($q, $n) => $q->where('lead_score', '>=', (int) $n))
            ->get();
    }

    private function recount(MarketingList $list): void
    {
        $list->update(['member_count' => $list->memberships()->count()]);
    }
}
