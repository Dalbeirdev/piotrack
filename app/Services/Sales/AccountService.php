<?php

namespace App\Services\Sales;

use App\Models\Contact;
use App\Models\TargetAccount;
use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Collection;

/**
 * Account-based marketing (ABM). Scores a target account by aggregating the
 * lead scores + buyer intent of its company's contacts, and exposes the buying
 * committee (the company's contacts). Enrichment/org-chart mapping arrive via
 * INTG connectors (Partial).
 */
class AccountService
{
    public function __construct(
        private IntentService $intent,
        private AuditLogger $audit,
    ) {}

    public function score(TargetAccount $account): int
    {
        $contacts = Contact::where('company_id', $account->company_id)->get();

        $leadScore = (int) $contacts->sum('lead_score');
        $intentScore = $contacts->reduce(fn (int $carry, Contact $c) => $carry + $this->intent->intentScore($c), 0);

        $score = $leadScore + $intentScore;
        $account->update(['account_score' => $score]);

        $this->audit->log('sales.account.rescored', context: ['score' => $score], resourceType: 'target_account', resourceId: (string) $account->id, organizationId: $account->organization_id);

        return $score;
    }

    /**
     * @return Collection<int, Contact>
     */
    public function buyingCommittee(TargetAccount $account): Collection
    {
        return Contact::where('company_id', $account->company_id)->get();
    }
}
