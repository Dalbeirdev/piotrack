<?php

namespace App\Services\Sales;

use App\Models\AlertRule;
use App\Models\Contact;
use App\Models\SalesAlert;
use App\Notifications\SalesAlertNotification;
use App\Support\AuditLogger;
use App\Support\CurrentOrganization;
use App\Support\NotificationDispatcher;

/**
 * Sales alerts (ALERT + INTENT-014/015). Evaluates a contact against active
 * alert rules and, when triggered, raises an in-app alert (deduped per
 * contact+type) and notifies the organization's owners. SMS/Slack/Teams
 * channels are Partial (need those connectors).
 */
class AlertService
{
    public function __construct(
        private IntentService $intent,
        private NotificationDispatcher $notifications,
        private CurrentOrganization $currentOrganization,
        private AuditLogger $audit,
    ) {}

    /**
     * Evaluate all threshold-based rules for a contact, returning the number of
     * alerts fired.
     */
    public function evaluate(Contact $contact): int
    {
        $fired = 0;

        foreach (AlertRule::where('is_active', true)->get() as $rule) {
            if ($this->triggered($rule, $contact) && $this->fire($rule->trigger, $contact)) {
                $fired++;
            }
        }

        return $fired;
    }

    /**
     * Raise an alert of the given type for a contact (deduped while unread).
     */
    public function fire(string $type, Contact $contact, ?string $message = null): bool
    {
        $exists = SalesAlert::where('contact_id', $contact->id)
            ->where('type', $type)->where('is_read', false)->exists();

        if ($exists) {
            return false;
        }

        $alert = SalesAlert::create([
            'contact_id' => $contact->id,
            'type' => $type,
            'message' => $message ?? $this->defaultMessage($type, $contact),
        ]);

        $organization = $this->currentOrganization->get();
        if ($organization !== null) {
            $this->notifications->toOrganizationOwners($organization, new SalesAlertNotification($alert->message));
        }

        $this->audit->log('sales.alert.created', context: ['type' => $type], resourceType: 'contact', resourceId: (string) $contact->id, organizationId: $contact->organization_id);

        return true;
    }

    private function triggered(AlertRule $rule, Contact $contact): bool
    {
        return match ($rule->trigger) {
            'score_threshold' => $contact->lead_score >= $rule->threshold,
            'high_intent' => $this->intent->intentScore($contact) >= $rule->threshold,
            default => false, // meeting_request/repeat_visit/bottom_funnel fire from their own events
        };
    }

    private function defaultMessage(string $type, Contact $contact): string
    {
        return match ($type) {
            'score_threshold' => "{$contact->fullName()} reached a hot lead score ({$contact->lead_score}).",
            'high_intent' => "{$contact->fullName()} is showing high buying intent.",
            'meeting_request' => "{$contact->fullName()} requested a meeting.",
            default => "New sales signal for {$contact->fullName()}.",
        };
    }
}
