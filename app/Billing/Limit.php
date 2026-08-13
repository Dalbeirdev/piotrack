<?php

namespace App\Billing;

/**
 * Numeric usage limits (ENTL-004). null on a plan entitlement means unlimited.
 * `members` is enforced today; the rest resolve now and are metered as their
 * modules arrive.
 */
enum Limit: string
{
    case Members = 'members';
    case Contacts = 'contacts';
    case Leads = 'leads';
    case Emails = 'emails';
    case Sms = 'sms';
    case AiCredits = 'ai_credits';
    case Keywords = 'keywords';
    case Competitors = 'competitors';
    case Locations = 'locations';
    case Websites = 'websites';
    case StorageMb = 'storage_mb';
    case ApiCalls = 'api_calls';
    case Automations = 'automations';
    case WorkflowExecutions = 'workflow_executions';
    case Reports = 'reports';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $l) => $l->value, self::cases());
    }
}
