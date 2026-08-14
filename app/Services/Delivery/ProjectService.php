<?php

namespace App\Services\Delivery;

use App\Models\Deliverable;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\ProjectTask;
use App\Models\Sprint;
use App\Models\User;
use App\Support\AuditLogger;
use RuntimeException;

/**
 * Project delivery (PROJ): projects, the delivery team staffed on them, weekly
 * sprints, tasks, deliverables and the client-approval workflow.
 */
class ProjectService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Project
    {
        return Project::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? 'planning',
            'health' => $data['health'] ?? 'on_track',
            'starts_on' => $data['starts_on'] ?? null,
            'ends_on' => $data['ends_on'] ?? null,
        ]);
    }

    /**
     * Staff a delivery role on the project (PROJ-001…008).
     */
    public function assign(Project $project, User $user, string $role): ProjectMember
    {
        if (! in_array($role, ProjectMember::ROLES, true)) {
            throw new RuntimeException("Unknown delivery role [{$role}].");
        }

        return ProjectMember::firstOrCreate([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'role' => $role,
        ], ['organization_id' => $project->organization_id]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function startSprint(Project $project, array $data): Sprint
    {
        return Sprint::create([
            'project_id' => $project->id,
            'name' => $data['name'],
            'goal' => $data['goal'] ?? null,
            'starts_on' => $data['starts_on'] ?? now()->toDateString(),
            'ends_on' => $data['ends_on'] ?? now()->addWeek()->toDateString(),
            'status' => $data['status'] ?? 'active',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function addTask(Project $project, array $data): ProjectTask
    {
        return ProjectTask::create([
            'project_id' => $project->id,
            'sprint_id' => $data['sprint_id'] ?? null,
            'assignee_id' => $data['assignee_id'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? 'todo',
            'priority' => $data['priority'] ?? 'normal',
            'due_on' => $data['due_on'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function addDeliverable(Project $project, array $data): Deliverable
    {
        return Deliverable::create([
            'project_id' => $project->id,
            'file_id' => $data['file_id'] ?? null,
            'title' => $data['title'],
            'type' => $data['type'] ?? 'document',
            'status' => $data['status'] ?? 'in_progress',
            'approval_status' => $data['approval_status'] ?? 'not_required',
            'notes' => $data['notes'] ?? null,
            'due_on' => $data['due_on'] ?? null,
            'client_visible' => (bool) ($data['client_visible'] ?? false),
        ]);
    }

    /**
     * Submit a deliverable for client approval. Submitting makes it visible to
     * the portal — a client cannot approve what they cannot see.
     */
    public function submitForApproval(Deliverable $deliverable): Deliverable
    {
        $deliverable->update([
            'status' => 'submitted',
            'approval_status' => Deliverable::APPROVAL_PENDING,
            'client_visible' => true,
        ]);

        return $deliverable->refresh();
    }

    public function approve(Deliverable $deliverable, User $approver): Deliverable
    {
        if ($deliverable->approval_status !== Deliverable::APPROVAL_PENDING) {
            throw new RuntimeException('Only a deliverable awaiting approval can be approved.');
        }

        $deliverable->update([
            'approval_status' => Deliverable::APPROVAL_APPROVED,
            'status' => 'delivered',
            'approved_by' => $approver->id,
            'approved_at' => now(),
            'rejection_reason' => null,
        ]);

        $this->audit->log('projects.deliverable.approved', context: ['title' => $deliverable->title],
            actorId: $approver->id, resourceType: 'deliverable', resourceId: (string) $deliverable->id);

        return $deliverable->refresh();
    }

    /**
     * Reject with a reason. The deliverable returns to in-progress so the work
     * can be revised — rejection is recoverable, never destructive.
     */
    public function reject(Deliverable $deliverable, User $approver, string $reason): Deliverable
    {
        if ($deliverable->approval_status !== Deliverable::APPROVAL_PENDING) {
            throw new RuntimeException('Only a deliverable awaiting approval can be rejected.');
        }

        $deliverable->update([
            'approval_status' => Deliverable::APPROVAL_REJECTED,
            'status' => 'in_progress',
            'approved_by' => $approver->id,
            'approved_at' => null,
            'rejection_reason' => $reason,
        ]);

        $this->audit->log('projects.deliverable.rejected', context: ['title' => $deliverable->title, 'reason' => $reason],
            actorId: $approver->id, resourceType: 'deliverable', resourceId: (string) $deliverable->id);

        return $deliverable->refresh();
    }

    /**
     * Delivery health for a project: task and deliverable progress.
     *
     * @return array<string, int|float>
     */
    public function progress(Project $project): array
    {
        $tasks = ProjectTask::where('project_id', $project->id)->count();
        $done = ProjectTask::where('project_id', $project->id)->where('status', 'done')->count();
        $overdue = ProjectTask::where('project_id', $project->id)
            ->whereNotNull('due_on')->whereDate('due_on', '<', now()->toDateString())
            ->where('status', '!=', 'done')->count();

        return [
            'tasks' => $tasks,
            'done' => $done,
            'overdue' => $overdue,
            'completion' => $tasks > 0 ? round($done / $tasks * 100, 2) : 0.0,
            'deliverables' => Deliverable::where('project_id', $project->id)->count(),
            'awaiting_approval' => Deliverable::where('project_id', $project->id)
                ->where('approval_status', Deliverable::APPROVAL_PENDING)->count(),
        ];
    }
}
