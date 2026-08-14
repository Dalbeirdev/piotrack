<?php

namespace App\Services\Ai;

use App\Models\AiAction;
use App\Models\Booking;
use App\Models\BookingPage;
use App\Models\Contact;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Human confirmation for sensitive AI actions (AIPF-006, ADR-0008).
 *
 * AI output that only informs a human is returned directly. AI output that would
 * CHANGE DATA or REACH A THIRD PARTY is never executed by the model: it is
 * recorded here as a proposal and stays `pending` until a user holding
 * `ai.actions.approve` confirms it. There is deliberately no configuration flag
 * that permits autonomous execution of a sensitive action.
 */
class AiActionService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * Record a proposed action. Proposing never performs it.
     *
     * @param  array<string, mixed>  $payload
     */
    public function propose(string $type, string $summary, array $payload, ?Model $subject = null): AiAction
    {
        $action = AiAction::create([
            'type' => $type,
            'summary' => $summary,
            'payload' => $payload,
            'subject_type' => $subject !== null ? $subject->getMorphClass() : null,
            'subject_id' => $subject?->getKey(),
            'status' => AiAction::STATUS_PENDING,
            'proposed_by' => request()->user()?->getAuthIdentifier(),
        ]);

        $this->audit->log('ai.action.proposed', context: ['type' => $type, 'summary' => $summary], resourceType: 'ai_action', resourceId: (string) $action->id);

        return $action;
    }

    /**
     * Confirm a pending action. Only a pending action can be confirmed.
     */
    public function confirm(AiAction $action, User $user): AiAction
    {
        if ($action->status !== AiAction::STATUS_PENDING) {
            throw new RuntimeException("Only a pending action can be confirmed; this one is [{$action->status}].");
        }

        $action->update([
            'status' => AiAction::STATUS_CONFIRMED,
            'confirmed_by' => $user->id,
            'confirmed_at' => now(),
        ]);

        $this->audit->log('ai.action.confirmed', context: ['type' => $action->type], actorId: $user->id, resourceType: 'ai_action', resourceId: (string) $action->id);

        return $action->refresh();
    }

    /**
     * Reject a pending action. Rejection is terminal — a rejected action can
     * never be confirmed or executed afterwards.
     */
    public function reject(AiAction $action, User $user): AiAction
    {
        if ($action->status !== AiAction::STATUS_PENDING) {
            throw new RuntimeException("Only a pending action can be rejected; this one is [{$action->status}].");
        }

        $action->update([
            'status' => AiAction::STATUS_REJECTED,
            'confirmed_by' => $user->id,
            'rejected_at' => now(),
        ]);

        $this->audit->log('ai.action.rejected', context: ['type' => $action->type], actorId: $user->id, resourceType: 'ai_action', resourceId: (string) $action->id);

        return $action->refresh();
    }

    /**
     * Perform a confirmed action. Refuses anything not in `confirmed` state, so
     * a pending or rejected action can never take effect, and re-running an
     * already-executed action is a no-op (idempotent by status).
     */
    public function execute(AiAction $action): AiAction
    {
        if ($action->status === AiAction::STATUS_EXECUTED) {
            return $action; // idempotent
        }

        if ($action->status !== AiAction::STATUS_CONFIRMED) {
            throw new RuntimeException("A [{$action->status}] action cannot be executed; it must be confirmed by a human first.");
        }

        $result = $this->perform($action);

        $action->update([
            'status' => AiAction::STATUS_EXECUTED,
            'executed_at' => now(),
            'result' => $result,
        ]);

        $this->audit->log('ai.action.executed', context: ['type' => $action->type, 'result' => $result], resourceType: 'ai_action', resourceId: (string) $action->id);

        return $action->refresh();
    }

    /**
     * Confirm and immediately perform, for a single-click approval in the UI.
     */
    public function confirmAndExecute(AiAction $action, User $user): AiAction
    {
        return $this->execute($this->confirm($action, $user));
    }

    /**
     * The handler for each action type. Only reached from `execute()`, which
     * has already established that a human confirmed it.
     */
    private function perform(AiAction $action): string
    {
        /** @var array<string, mixed> $payload */
        $payload = $action->payload;

        return match ($action->type) {
            'update_crm' => $this->applyCrmUpdate($action, $payload),
            'book_meeting' => $this->bookMeeting($action, $payload),
            // `send_email` deliberately records intent only: outbound delivery
            // runs through the Stage 6 messaging pipeline once a real provider
            // is configured, so nothing is silently sent from here.
            'send_email' => 'Approved for sending; queued to the messaging pipeline.',
            default => 'Approved.',
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyCrmUpdate(AiAction $action, array $payload): string
    {
        $contact = Contact::find($action->subject_id);
        if ($contact === null) {
            return 'Contact no longer exists; nothing applied.';
        }

        // Only an explicit allow-list of fields may be written by an AI proposal.
        $allowed = ['title', 'phone', 'lifecycle_stage', 'lead_source', 'campaign'];
        /** @var array<string, mixed> $changes */
        $changes = array_intersect_key($payload['changes'] ?? [], array_flip($allowed));

        if ($changes === []) {
            return 'No permitted fields in the proposal; nothing applied.';
        }

        $contact->update($changes);

        return 'Updated: '.implode(', ', array_keys($changes));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function bookMeeting(AiAction $action, array $payload): string
    {
        $contact = Contact::find($action->subject_id);

        // A booking always belongs to a page; fall back to the tenant's first
        // active one rather than failing at execution time.
        $pageId = $payload['booking_page_id'] ?? BookingPage::where('is_active', true)->value('id');
        if ($pageId === null) {
            return 'No active booking page configured; nothing booked.';
        }

        $booking = Booking::create([
            'booking_page_id' => $pageId,
            'contact_id' => $contact?->id,
            'owner_id' => $payload['owner_id'] ?? null,
            'name' => (string) ($payload['name'] ?? ($contact !== null ? $contact->fullName() : 'Prospect')),
            'email' => (string) ($payload['email'] ?? ($contact !== null ? $contact->email : '')),
            'scheduled_at' => $payload['scheduled_at'] ?? now()->addDay(),
            'status' => 'booked',
            'source' => 'ai_agent',
        ]);

        return "Meeting booked (#{$booking->id}).";
    }
}
