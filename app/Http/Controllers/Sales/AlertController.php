<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\AlertRule;
use App\Models\SalesAlert;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AlertController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('sales/alerts/index', [
            'rules' => AlertRule::latest('id')->get()->map(fn (AlertRule $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'trigger' => $r->trigger,
                'threshold' => $r->threshold,
                'channel' => $r->channel,
                'is_active' => $r->is_active,
            ]),
            'alerts' => SalesAlert::with('contact:id,first_name,last_name')->latest('id')->limit(100)->get()
                ->map(fn (SalesAlert $a) => [
                    'id' => $a->id,
                    'type' => $a->type,
                    'message' => $a->message,
                    'is_read' => $a->is_read,
                    'contact' => $a->contact?->fullName(),
                    'created_at' => $a->created_at?->toIso8601String(),
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        AlertRule::create($request->validate([
            'name' => ['required', 'string', 'max:150'],
            'trigger' => ['required', Rule::in(['score_threshold', 'high_intent', 'meeting_request', 'repeat_visit', 'bottom_funnel'])],
            'threshold' => ['required', 'integer', 'min:0'],
            'channel' => ['required', Rule::in(['in_app', 'email'])],
            'is_active' => ['boolean'],
        ]));

        return back()->with('status', __('Alert rule created.'));
    }

    public function destroy(AlertRule $rule): RedirectResponse
    {
        $rule->delete();

        return back()->with('status', __('Alert rule removed.'));
    }

    public function markRead(SalesAlert $alert): RedirectResponse
    {
        $alert->update(['is_read' => true]);

        return back()->with('status', __('Alert marked as read.'));
    }
}
