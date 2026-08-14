<?php

namespace App\Services\Advertising;

use App\Models\Contact;
use App\Models\RetargetingAudience;
use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Builds retargeting audiences from first-party CRM data (RETG). An audience is
 * resolved from its source (marketing list / funnel stage / behavior rules /
 * all contacts), with converted customers excluded when requested; the sync
 * payload is a list of hashed emails ready for a platform custom-audience push.
 */
class RetargetingService
{
    public function __construct(private AuditLogger $audit) {}

    public function rebuild(RetargetingAudience $audience): int
    {
        $count = $this->members($audience)->count();
        $audience->update(['member_count' => $count]);

        $this->audit->log('ads.retargeting.rebuilt', context: ['audience' => $audience->name, 'members' => $count], resourceType: 'retargeting_audience', resourceId: (string) $audience->id, organizationId: $audience->organization_id);

        return $count;
    }

    /**
     * @return Collection<int, Contact>
     */
    public function members(RetargetingAudience $audience): Collection
    {
        $rules = $audience->rules ?? [];

        $query = match ($audience->source) {
            'list' => $audience->marketing_list_id !== null
                ? Contact::whereHas('lists', fn (Builder $q) => $q->where('marketing_lists.id', $audience->marketing_list_id))
                : Contact::whereRaw('1 = 0'),
            'funnel_stage' => Contact::where('lifecycle_stage', (string) ($rules['lifecycle_stage'] ?? 'lead')),
            'behavior' => Contact::query()
                ->when($rules['min_lead_score'] ?? null, fn (Builder $q, $n) => $q->where('lead_score', '>=', (int) $n))
                ->when($rules['lead_source'] ?? null, fn (Builder $q, $s) => $q->where('lead_source', $s)),
            default => Contact::query(), // all_contacts
        };

        // Conversion exclusion (RETG-016): drop existing customers.
        if ($audience->exclude_converted) {
            $query->where('lifecycle_stage', '!=', 'customer');
        }

        return $query->get();
    }

    /**
     * Hashed-email payload for a platform custom-audience upload. Actual push
     * happens through the platform connector (Planned).
     *
     * @return list<string>
     */
    public function syncPayload(RetargetingAudience $audience): array
    {
        return $this->members($audience)
            ->filter(fn (Contact $c) => ! empty($c->email))
            ->map(fn (Contact $c) => hash('sha256', mb_strtolower(trim((string) $c->email))))
            ->values()
            ->all();
    }
}
