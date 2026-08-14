<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\ScoringRule;
use App\Services\Sales\LeadScoringService;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ScoringController extends Controller
{
    public function __construct(
        private LeadScoringService $scoring,
        private AuditLogger $audit,
    ) {}

    public function index(): Response
    {
        return Inertia::render('sales/scoring/index', [
            'rules' => ScoringRule::latest('id')->get()->map(fn (ScoringRule $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'category' => $r->category,
                'attribute' => $r->attribute,
                'operator' => $r->operator,
                'value' => $r->value,
                'points' => $r->points,
                'is_active' => $r->is_active,
            ]),
            'contacts' => Contact::orderByDesc('lead_score')->limit(50)->get()->map(fn (Contact $c) => [
                'id' => $c->id,
                'name' => $c->fullName(),
                'email' => $c->email,
                'lead_score' => $c->lead_score,
                'temperature' => $this->scoring->temperature($c->lead_score),
                'lifecycle_stage' => $c->lifecycle_stage,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $rule = ScoringRule::create($this->validateData($request));
        $this->audit->log('sales.rule.created', context: ['name' => $rule->name], resourceType: 'scoring_rule', resourceId: (string) $rule->id, organizationId: $rule->organization_id);

        return back()->with('status', __('Scoring rule created.'));
    }

    public function update(Request $request, ScoringRule $rule): RedirectResponse
    {
        $rule->update($this->validateData($request));

        return back()->with('status', __('Scoring rule updated.'));
    }

    public function destroy(ScoringRule $rule): RedirectResponse
    {
        $rule->delete();

        return back()->with('status', __('Scoring rule removed.'));
    }

    public function recompute(): RedirectResponse
    {
        $count = $this->scoring->recomputeAll();

        return back()->with('status', __('Recomputed scores for :n contacts.', ['n' => $count]));
    }

    /**
     * @return array<string, mixed>
     */
    private function validateData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'category' => ['required', Rule::in(['demographic', 'firmographic', 'behavioral', 'intent'])],
            'attribute' => ['required', Rule::in(['lifecycle_stage', 'lead_source', 'title', 'email_opt_in', 'has_company', 'intent_score'])],
            'operator' => ['required', Rule::in(['equals', 'contains', 'gte', 'is_true'])],
            'value' => ['nullable', 'string', 'max:200'],
            'points' => ['required', 'integer', 'min:-100', 'max:100'],
            'is_active' => ['boolean'],
        ]);
    }
}
