<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function __construct(
        private AuditLogger $audit,
        private CurrentOrganization $currentOrganization,
    ) {}

    public function index(): Response
    {
        $organization = $this->currentOrganization->get();

        // Teams are tenant-scoped by the global scope; eager-load members.
        $teams = Team::with('members:id,name,email')
            ->orderBy('name')
            ->get()
            ->map(fn (Team $team) => [
                'id' => $team->id,
                'name' => $team->name,
                'members' => $team->members->map(fn (User $m) => [
                    'id' => $m->id,
                    'name' => $m->name,
                    'email' => $m->email,
                ])->all(),
            ]);

        $organizationMembers = $organization->members()
            ->orderBy('name')
            ->get(['users.id', 'users.name', 'users.email'])
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email]);

        return Inertia::render('settings/teams', [
            'teams' => $teams,
            'organizationMembers' => $organizationMembers,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $team = Team::create(['name' => $validated['name']]);

        $this->audit->log(
            'team.created',
            context: ['name' => $team->name],
            resourceType: 'team',
            resourceId: (string) $team->id,
        );

        return back();
    }

    public function update(Request $request, Team $team): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $team->update(['name' => $validated['name']]);

        $this->audit->log(
            'team.updated',
            context: ['name' => $team->name],
            resourceType: 'team',
            resourceId: (string) $team->id,
        );

        return back();
    }

    public function destroy(Team $team): RedirectResponse
    {
        $this->audit->log(
            'team.deleted',
            context: ['name' => $team->name],
            resourceType: 'team',
            resourceId: (string) $team->id,
        );

        $team->delete();

        return back();
    }

    public function addMember(Request $request, Team $team): RedirectResponse
    {
        $organization = $this->currentOrganization->get();

        $validated = $request->validate([
            'user_id' => [
                'required',
                Rule::exists('organization_user', 'user_id')->where('organization_id', $organization->id),
            ],
        ]);

        $team->members()->syncWithoutDetaching([$validated['user_id']]);

        $this->audit->log(
            'team.member_added',
            context: ['team_id' => $team->id, 'user_id' => (int) $validated['user_id']],
            resourceType: 'team',
            resourceId: (string) $team->id,
        );

        return back();
    }

    public function removeMember(Team $team, User $member): RedirectResponse
    {
        $team->members()->detach($member->id);

        $this->audit->log(
            'team.member_removed',
            context: ['team_id' => $team->id, 'user_id' => $member->id],
            resourceType: 'team',
            resourceId: (string) $team->id,
        );

        return back();
    }
}
