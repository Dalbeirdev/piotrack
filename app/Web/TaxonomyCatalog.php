<?php

namespace App\Web;

/**
 * The code source of truth for the MSP service lines (SVC-001…024) and industry
 * verticals (VERT-001…012), provisioned per organization on creation and
 * editable afterwards.
 *
 * These are a taxonomy, not 36 separate features: a service line or vertical is
 * useful because pages, campaigns, keywords and content TARGET it. The register
 * notes say so rather than implying 24 bespoke campaigns ship in the box.
 */
class TaxonomyCatalog
{
    /**
     * @return list<array{key: string, name: string, category: string}>
     */
    public static function serviceLines(): array
    {
        return [
            ['key' => 'managed_it', 'name' => 'Managed IT', 'category' => 'managed_it'],
            ['key' => 'co_managed_it', 'name' => 'Co-Managed IT', 'category' => 'managed_it'],
            ['key' => 'cybersecurity', 'name' => 'Cybersecurity', 'category' => 'security'],
            ['key' => 'mssp', 'name' => 'MSSP', 'category' => 'security'],
            ['key' => 'soc', 'name' => 'SOC', 'category' => 'security'],
            ['key' => 'siem', 'name' => 'SIEM', 'category' => 'security'],
            ['key' => 'mdr', 'name' => 'MDR', 'category' => 'security'],
            ['key' => 'edr', 'name' => 'EDR', 'category' => 'security'],
            ['key' => 'cloud', 'name' => 'Cloud', 'category' => 'cloud'],
            ['key' => 'microsoft_365', 'name' => 'Microsoft 365', 'category' => 'cloud'],
            ['key' => 'azure', 'name' => 'Azure', 'category' => 'cloud'],
            ['key' => 'backup', 'name' => 'Backup', 'category' => 'continuity'],
            ['key' => 'disaster_recovery', 'name' => 'Disaster Recovery', 'category' => 'continuity'],
            ['key' => 'business_continuity', 'name' => 'Business Continuity', 'category' => 'continuity'],
            ['key' => 'voip', 'name' => 'VoIP', 'category' => 'comms'],
            ['key' => 'help_desk', 'name' => 'Help Desk', 'category' => 'managed_it'],
            ['key' => 'it_consulting', 'name' => 'IT Consulting', 'category' => 'advisory'],
            ['key' => 'vcio', 'name' => 'vCIO', 'category' => 'advisory'],
            ['key' => 'compliance', 'name' => 'Compliance', 'category' => 'compliance'],
            ['key' => 'cmmc', 'name' => 'CMMC', 'category' => 'compliance'],
            ['key' => 'hipaa', 'name' => 'HIPAA', 'category' => 'compliance'],
            ['key' => 'pci', 'name' => 'PCI', 'category' => 'compliance'],
            ['key' => 'nist', 'name' => 'NIST', 'category' => 'compliance'],
            ['key' => 'zero_trust', 'name' => 'Zero Trust', 'category' => 'security'],
        ];
    }

    /**
     * @return list<array{key: string, name: string, compliance_notes: string|null}>
     */
    public static function verticals(): array
    {
        return [
            ['key' => 'healthcare', 'name' => 'Healthcare', 'compliance_notes' => 'HIPAA safeguards, PHI handling, BAA requirements.'],
            ['key' => 'legal', 'name' => 'Legal', 'compliance_notes' => 'Client confidentiality, privilege, bar association guidance.'],
            ['key' => 'financial_services', 'name' => 'Financial Services', 'compliance_notes' => 'GLBA, SOX, FINRA/SEC recordkeeping.'],
            ['key' => 'manufacturing', 'name' => 'Manufacturing', 'compliance_notes' => 'OT/IT convergence, NIST 800-171 for supply chain.'],
            ['key' => 'construction', 'name' => 'Construction', 'compliance_notes' => 'Jobsite connectivity, mobile workforce security.'],
            ['key' => 'nonprofit', 'name' => 'Nonprofit', 'compliance_notes' => 'Donor data protection, grant reporting.'],
            ['key' => 'education', 'name' => 'Education', 'compliance_notes' => 'FERPA, CIPA, student data privacy.'],
            ['key' => 'professional_services', 'name' => 'Professional Services', 'compliance_notes' => 'Client data confidentiality, billable uptime.'],
            ['key' => 'government_contractors', 'name' => 'Government Contractors', 'compliance_notes' => 'CMMC, NIST 800-171, DFARS.'],
            ['key' => 'retail', 'name' => 'Retail', 'compliance_notes' => 'PCI DSS, POS security, seasonal scaling.'],
            ['key' => 'accounting', 'name' => 'Accounting', 'compliance_notes' => 'IRS Publication 4557, WISP requirements.'],
            ['key' => 'insurance', 'name' => 'Insurance', 'compliance_notes' => 'NAIC data security model law, PII handling.'],
        ];
    }
}
