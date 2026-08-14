<?php

namespace App\Http\Controllers\Ai;

use App\Http\Controllers\Controller;
use App\Models\AiAction;
use App\Services\Ai\AiActionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The approval queue for sensitive AI proposals (AIPF-006). Confirming is gated
 * on `ai.actions.approve`; the full payload is surfaced so the approver sees
 * exactly what they are authorizing.
 */
class AiActionController extends Controller
{
    public function __construct(private AiActionService $actions) {}

    public function index(): Response
    {
        return Inertia::render('ai/actions', [
            'actions' => AiAction::latest('id')->limit(100)->get()->map(fn (AiAction $a) => [
                'id' => $a->id,
                'type' => $a->type,
                'summary' => $a->summary,
                'payload' => $a->payload,
                'status' => $a->status,
                'sensitive' => $a->isSensitive(),
                'result' => $a->result,
                'confirmed_at' => $a->confirmed_at?->toIso8601String(),
                'executed_at' => $a->executed_at?->toIso8601String(),
                'created_at' => $a->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function approve(Request $request, AiAction $action): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $this->actions->confirmAndExecute($action, $user);

        return back()->with('status', __('Action approved and applied.'));
    }

    public function reject(Request $request, AiAction $action): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $this->actions->reject($action, $user);

        return back()->with('status', __('Action rejected.'));
    }
}
