<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\Pipeline;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Converts a lead into a contact (+ company) and optionally opens a deal
 * (CRM-003 → 005/008). Reuses an existing company (by name) and contact (by
 * email) to avoid duplicates.
 */
class LeadConversionService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @return array{contact: Contact, company: ?Company, deal: ?Deal}
     */
    public function convert(Lead $lead, bool $createDeal = false, ?int $dealValue = null): array
    {
        return DB::transaction(function () use ($lead, $createDeal, $dealValue) {
            $company = $this->resolveCompany($lead);
            $contact = $this->resolveContact($lead, $company);

            $deal = null;
            if ($createDeal) {
                $deal = $this->openDeal($lead, $contact, $company, $dealValue);
            }

            $lead->update([
                'status' => 'converted',
                'converted_contact_id' => $contact->id,
                'converted_deal_id' => $deal?->id,
                'converted_at' => now(),
            ]);

            $this->audit->log('crm.lead.converted', context: [
                'contact_id' => $contact->id,
                'deal_id' => $deal?->id,
            ], resourceType: 'lead', resourceId: (string) $lead->id, organizationId: $lead->organization_id);

            return ['contact' => $contact, 'company' => $company, 'deal' => $deal];
        });
    }

    private function resolveCompany(Lead $lead): ?Company
    {
        if (empty($lead->company_name)) {
            return null;
        }

        return Company::firstOrCreate(
            ['organization_id' => $lead->organization_id, 'name' => $lead->company_name],
            ['owner_id' => $lead->owner_id],
        );
    }

    private function resolveContact(Lead $lead, ?Company $company): Contact
    {
        $existing = ! empty($lead->email)
            ? Contact::where('organization_id', $lead->organization_id)->where('email', $lead->email)->first()
            : null;

        if ($existing !== null) {
            return $existing;
        }

        return Contact::create([
            'organization_id' => $lead->organization_id,
            'company_id' => $company?->id,
            'first_name' => $lead->first_name,
            'last_name' => $lead->last_name,
            'email' => $lead->email,
            'phone' => $lead->phone,
            'lead_source' => $lead->source,
            'campaign' => $lead->campaign,
            'owner_id' => $lead->owner_id,
        ]);
    }

    private function openDeal(Lead $lead, Contact $contact, ?Company $company, ?int $value): Deal
    {
        $pipeline = Pipeline::where('organization_id', $lead->organization_id)
            ->where('is_default', true)
            ->with('stages')
            ->firstOrFail();

        $firstStage = $pipeline->stages->firstWhere('is_won', false) ?? $pipeline->stages->first();

        return Deal::create([
            'organization_id' => $lead->organization_id,
            'pipeline_id' => $pipeline->id,
            'stage_id' => $firstStage->id,
            'name' => $lead->fullName().' opportunity',
            'contact_id' => $contact->id,
            'company_id' => $company?->id,
            'value' => $value ?? 0,
            'status' => 'open',
            'lead_source' => $lead->source,
            'campaign' => $lead->campaign,
            'owner_id' => $lead->owner_id,
        ]);
    }
}
