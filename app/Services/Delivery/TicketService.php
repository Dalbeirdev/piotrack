<?php

namespace App\Services\Delivery;

use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Support\AuditLogger;

/**
 * Support tickets (SUPP-002). Internal notes are stored on the same thread but
 * are never exposed through the client portal.
 */
class TicketService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function open(array $data, ?User $requester = null): Ticket
    {
        $ticket = Ticket::create([
            'requester_id' => $requester?->id,
            'subject' => $data['subject'],
            'body' => $data['body'],
            'priority' => $data['priority'] ?? 'normal',
            'category' => $data['category'] ?? null,
            'status' => 'open',
        ]);

        $this->audit->log('support.ticket.created', context: ['subject' => $ticket->subject, 'priority' => $ticket->priority],
            resourceType: 'ticket', resourceId: (string) $ticket->id);

        return $ticket;
    }

    public function reply(Ticket $ticket, string $body, ?User $author = null, bool $internal = false): TicketMessage
    {
        $message = TicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => $author?->id,
            'body' => $body,
            'is_internal' => $internal,
        ]);

        if ($ticket->status === 'resolved' || $ticket->status === 'closed') {
            $ticket->update(['status' => 'open', 'resolved_at' => null]);
        }

        return $message;
    }

    public function assign(Ticket $ticket, User $assignee): Ticket
    {
        $ticket->update(['assignee_id' => $assignee->id, 'status' => 'pending']);

        return $ticket->refresh();
    }

    public function resolve(Ticket $ticket): Ticket
    {
        $ticket->update(['status' => 'resolved', 'resolved_at' => now()]);

        $this->audit->log('support.ticket.resolved', context: ['subject' => $ticket->subject],
            resourceType: 'ticket', resourceId: (string) $ticket->id);

        return $ticket->refresh();
    }

    /**
     * The thread as the client may see it — internal notes removed.
     *
     * @return list<array<string, mixed>>
     */
    public function clientThread(Ticket $ticket): array
    {
        return $ticket->messages()->where('is_internal', false)->orderBy('id')->get()
            ->map(fn (TicketMessage $m) => [
                'id' => $m->id,
                'body' => $m->body,
                'user_id' => $m->user_id,
                'created_at' => $m->created_at?->toIso8601String(),
            ])->all();
    }
}
