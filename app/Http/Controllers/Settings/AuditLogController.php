<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\CurrentOrganization;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Organization-scoped audit log viewer (AUDIT-006). Only shows events for the
 * current organization; platform-wide viewing arrives with the platform admin
 * area (Stage 13).
 */
class AuditLogController extends Controller
{
    public function __construct(private CurrentOrganization $currentOrganization) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'action' => ['nullable', 'string', 'max:100'],
            'actor' => ['nullable', 'string', 'max:100'],
        ]);

        $logs = AuditLog::query()
            ->where('organization_id', $this->currentOrganization->id())
            ->with('actor:id,name,email')
            ->when($filters['action'] ?? null, fn ($q, $action) => $q->whereLike('action', "%{$action}%"))
            ->when($filters['actor'] ?? null, fn ($q, $actor) => $q->whereHas(
                'actor',
                fn ($a) => $a->whereLike('name', "%{$actor}%")->orWhereLike('email', "%{$actor}%"),
            ))
            ->latest('created_at')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (AuditLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'actor' => $log->actor !== null ? ['name' => $log->actor->name, 'email' => $log->actor->email] : null,
                'resource_type' => $log->resource_type,
                'resource_id' => $log->resource_id,
                'context' => $log->context,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at,
            ]);

        return Inertia::render('settings/audit-log', [
            'logs' => $logs,
            'filters' => $filters,
        ]);
    }
}
