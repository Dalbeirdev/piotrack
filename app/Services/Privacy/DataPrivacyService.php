<?php

namespace App\Services\Privacy;

use App\Models\Company;
use App\Models\Contact;
use App\Models\DataRequest;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\Organization;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\SecurityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Data export and erasure (PRIV-003).
 *
 * Deletion here is real deletion, not a flag: an erasure request that leaves the
 * data in place is a lie to the data subject and to the regulator. Organization
 * deletion relies on the `cascadeOnDelete` foreign keys every tenant table was
 * built with, so removing the organization removes its records rather than
 * orphaning them.
 */
class DataPrivacyService
{
    public function __construct(
        private AuditLogger $audit,
        private SecurityLogger $security,
    ) {}

    /**
     * Export an organization's data as JSON and record the request.
     */
    public function exportOrganization(Organization $organization, ?User $requestedBy = null): DataRequest
    {
        $request = DataRequest::create([
            'organization_id' => $organization->id,
            'requested_by' => $requestedBy?->id,
            'type' => 'export',
            'status' => 'pending',
        ]);

        try {
            $payload = $this->organizationPayload($organization);
            $path = "exports/organization-{$organization->id}-".now()->format('Ymd-His').'.json';

            Storage::disk('local')->put($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $request->update(['status' => 'completed', 'file_path' => $path, 'completed_at' => now()]);

            $this->audit->log('privacy.data.exported', context: ['organization_id' => $organization->id],
                resourceType: 'data_request', resourceId: (string) $request->id, organizationId: $organization->id);
        } catch (Throwable $e) {
            $request->update(['status' => 'failed', 'error' => $e->getMessage()]);
        }

        return $request->refresh();
    }

    /**
     * Everything we hold for the organization, in a portable shape.
     *
     * @return array<string, mixed>
     */
    public function organizationPayload(Organization $organization): array
    {
        return [
            'exported_at' => now()->toIso8601String(),
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
                'created_at' => $organization->created_at?->toIso8601String(),
            ],
            'members' => $organization->members()->get(['users.id', 'users.name', 'users.email'])
                ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email])->all(),
            'contacts' => Contact::withoutGlobalScope('tenant')->where('organization_id', $organization->id)
                ->get()->map->toArray()->all(),
            'companies' => Company::withoutGlobalScope('tenant')->where('organization_id', $organization->id)
                ->get()->map->toArray()->all(),
            'leads' => Lead::withoutGlobalScope('tenant')->where('organization_id', $organization->id)
                ->get()->map->toArray()->all(),
            'deals' => Deal::withoutGlobalScope('tenant')->where('organization_id', $organization->id)
                ->get()->map->toArray()->all(),
        ];
    }

    /**
     * Export everything tied to one person (PRIV-003, data-subject access).
     *
     * @return array<string, mixed>
     */
    public function userPayload(User $user): array
    {
        return [
            'exported_at' => now()->toIso8601String(),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at?->toIso8601String(),
            ],
            'organizations' => $user->organizations()->get(['organizations.id', 'organizations.name'])
                ->map(fn (Organization $o) => ['id' => $o->id, 'name' => $o->name])->all(),
            'policy_acceptances' => $user->policyAcceptances()->get(['policy', 'version', 'accepted_at'])->toArray(),
        ];
    }

    /**
     * Delete a user account. Their organization memberships go with them; an
     * organization they solely own is NOT silently destroyed — that requires an
     * explicit organization deletion so ownership is a deliberate decision.
     *
     * @return array{deleted: bool, blocked_by: list<string>}
     */
    public function deleteUser(User $user): array
    {
        $soleOwned = $user->organizations()
            ->wherePivot('role', 'owner')
            ->get()
            ->filter(fn (Organization $o) => $o->members()->wherePivot('role', 'owner')->count() === 1);

        if ($soleOwned->isNotEmpty()) {
            return [
                'deleted' => false,
                'blocked_by' => $soleOwned->map(fn (Organization $o) => $o->name)->values()->all(),
            ];
        }

        $this->audit->log('privacy.user.deleted', context: ['user_id' => $user->id, 'email' => $user->email],
            resourceType: 'user', resourceId: (string) $user->id);
        $this->security->record('security.account_deleted', ['user_id' => $user->id]);

        $user->delete();

        return ['deleted' => true, 'blocked_by' => []];
    }

    /**
     * Delete an organization and everything belonging to it. Every tenant table
     * was created with `cascadeOnDelete` on `organization_id`, so this is a real
     * erasure rather than a flag.
     */
    public function deleteOrganization(Organization $organization, ?User $requestedBy = null): DataRequest
    {
        $request = DataRequest::create([
            'organization_id' => $organization->id,
            'requested_by' => $requestedBy?->id,
            'type' => 'delete_organization',
            'status' => 'pending',
        ]);

        $name = $organization->name;
        $id = $organization->id;

        try {
            // Organization soft-deletes, so `delete()` would only stamp
            // `deleted_at` — the row would survive, the cascadeOnDelete foreign
            // keys would never fire, and every contact, deal and message would
            // remain in the database. An erasure request must actually erase.
            DB::transaction(fn () => $organization->forceDelete());

            // The organization row is gone, so the audit entry cannot reference it.
            $this->audit->log('privacy.organization.deleted', context: ['organization_id' => $id, 'name' => $name],
                resourceType: 'organization', resourceId: (string) $id, organizationId: null);
            $this->security->record('security.account_deleted', ['organization_id' => $id]);

            $request->forceFill(['organization_id' => null, 'status' => 'completed', 'completed_at' => now()])->save();
        } catch (Throwable $e) {
            $request->update(['status' => 'failed', 'error' => $e->getMessage()]);
        }

        return $request->refresh();
    }
}
