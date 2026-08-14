<?php

namespace App\Services\Delivery;

use App\Models\Deliverable;
use App\Models\File;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\Ticket;
use App\Services\Analytics\AnalyticsService;
use App\Services\Strategy\KpiTargetService;
use Illuminate\Database\Eloquent\Builder;

/**
 * The client portal read model (PORTAL). Everything here is deliberately
 * narrowed: a client sees their organization's delivery work and results, and
 * **only deliverables explicitly marked `client_visible`**. Internal ticket
 * notes, unpublished deliverables and every administrative surface stay out.
 *
 * Tenant scoping still applies on top of this via the global scope — this class
 * narrows further within the tenant, it does not replace tenant isolation.
 */
class PortalService
{
    public function __construct(
        private AnalyticsService $analytics,
        private KpiTargetService $targets,
        private TicketService $tickets,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function dashboard(): array
    {
        $funnel = $this->analytics->funnel();

        return [
            'projects' => Project::where('status', '!=', 'completed')->count(),
            'open_tasks' => ProjectTask::where('status', '!=', 'done')->count(),
            'awaiting_approval' => $this->visibleDeliverables()
                ->where('approval_status', Deliverable::APPROVAL_PENDING)->count(),
            'open_tickets' => Ticket::whereIn('status', ['open', 'pending'])->count(),
            // PORTAL-016/017/018 — the client's KPI, lead and revenue view.
            'kpis' => $this->targets->attainment(),
            'leads' => [
                'total' => $funnel['leads'],
                'mqls' => $funnel['mqls'],
                'sqls' => $funnel['sqls'],
                'meetings' => $funnel['meetings'],
            ],
            'revenue' => $this->analytics->revenue(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function projects(): array
    {
        return Project::latest('id')->get()->map(fn (Project $p) => [
            'id' => $p->id,
            'name' => $p->name,
            'description' => $p->description,
            'status' => $p->status,
            'health' => $p->health,
            'starts_on' => $p->starts_on?->toDateString(),
            'ends_on' => $p->ends_on?->toDateString(),
        ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function tasks(): array
    {
        return ProjectTask::latest('id')->limit(100)->get()->map(fn (ProjectTask $t) => [
            'id' => $t->id,
            'project_id' => $t->project_id,
            'title' => $t->title,
            'status' => $t->status,
            'priority' => $t->priority,
            'due_on' => $t->due_on?->toDateString(),
        ])->all();
    }

    /**
     * Only client-visible deliverables ever leave this method.
     *
     * @return Builder<Deliverable>
     */
    public function visibleDeliverables()
    {
        return Deliverable::query()->where('client_visible', true);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function deliverables(): array
    {
        return $this->visibleDeliverables()->latest('id')->get()->map(fn (Deliverable $d) => [
            'id' => $d->id,
            'project_id' => $d->project_id,
            'title' => $d->title,
            'type' => $d->type,
            'status' => $d->status,
            'approval_status' => $d->approval_status,
            'due_on' => $d->due_on?->toDateString(),
            'approved_at' => $d->approved_at?->toIso8601String(),
            'rejection_reason' => $d->rejection_reason,
        ])->all();
    }

    /**
     * Tickets with internal notes stripped from every thread.
     *
     * @return list<array<string, mixed>>
     */
    public function ticketsForClient(): array
    {
        return Ticket::latest('id')->limit(50)->get()->map(fn (Ticket $t) => [
            'id' => $t->id,
            'subject' => $t->subject,
            'status' => $t->status,
            'priority' => $t->priority,
            'created_at' => $t->created_at?->toIso8601String(),
            'messages' => $this->tickets->clientThread($t),
        ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function files(): array
    {
        return File::latest('id')->limit(100)->get()->map(fn (File $f) => [
            'id' => $f->id,
            'name' => $f->name,
            'size' => $f->size,
            'created_at' => $f->created_at?->toIso8601String(),
        ])->all();
    }
}
