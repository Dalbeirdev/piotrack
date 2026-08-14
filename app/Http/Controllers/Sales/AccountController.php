<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\TargetAccount;
use App\Services\Sales\AccountService;
use App\Support\AuditLogger;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function __construct(
        private AccountService $accounts,
        private CurrentOrganization $currentOrganization,
        private AuditLogger $audit,
    ) {}

    public function index(): Response
    {
        return Inertia::render('sales/accounts/index', [
            'accounts' => TargetAccount::with('company:id,name')->orderBy('tier')->orderByDesc('account_score')->get()
                ->map(fn (TargetAccount $a) => [
                    'id' => $a->id,
                    'company' => $a->company?->name,
                    'tier' => $a->tier,
                    'status' => $a->status,
                    'account_score' => $a->account_score,
                    'committee' => $this->accounts->buyingCommittee($a)->count(),
                ]),
            'companies' => Company::orderBy('name')->limit(200)->get(['id', 'name'])
                ->map(fn (Company $c) => ['id' => $c->id, 'name' => $c->name]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'company_id' => ['required', Rule::exists('companies', 'id')->where('organization_id', $this->currentOrganization->id())],
            'tier' => ['required', 'integer', 'min:1', 'max:3'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if (TargetAccount::where('company_id', $data['company_id'])->exists()) {
            return back()->withErrors(['company_id' => __('That company is already a target account.')]);
        }

        $account = TargetAccount::create($data);
        $this->accounts->score($account);
        $this->audit->log('sales.account.created', context: ['tier' => $account->tier], resourceType: 'target_account', resourceId: (string) $account->id, organizationId: $account->organization_id);

        return back()->with('status', __('Target account added.'));
    }

    public function update(Request $request, TargetAccount $account): RedirectResponse
    {
        $account->update($request->validate([
            'tier' => ['required', 'integer', 'min:1', 'max:3'],
            'status' => ['required', Rule::in(['targeted', 'engaged', 'opportunity', 'won', 'lost'])],
        ]));

        return back()->with('status', __('Account updated.'));
    }

    public function rescore(TargetAccount $account): RedirectResponse
    {
        $score = $this->accounts->score($account);

        return back()->with('status', __('Account rescored (:n).', ['n' => $score]));
    }

    public function destroy(TargetAccount $account): RedirectResponse
    {
        $account->delete();

        return back()->with('status', __('Account removed.'));
    }
}
