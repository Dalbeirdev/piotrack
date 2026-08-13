<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\MarketingList;
use App\Services\Marketing\ListService;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ListController extends Controller
{
    public function __construct(
        private ListService $lists,
        private AuditLogger $audit,
    ) {}

    public function index(): Response
    {
        return Inertia::render('marketing/lists/index', [
            'lists' => MarketingList::latest('id')->get()->map(fn (MarketingList $l) => [
                'id' => $l->id,
                'name' => $l->name,
                'description' => $l->description,
                'type' => $l->type,
                'member_count' => $l->member_count,
            ]),
        ]);
    }

    public function show(MarketingList $list): Response
    {
        return Inertia::render('marketing/lists/show', [
            'list' => [
                'id' => $list->id,
                'name' => $list->name,
                'description' => $list->description,
                'type' => $list->type,
                'criteria' => $list->criteria,
                'member_count' => $list->member_count,
            ],
            'members' => $this->lists->members($list)->map(fn (Contact $c) => [
                'id' => $c->id,
                'name' => $c->fullName(),
                'email' => $c->email,
                'lifecycle_stage' => $c->lifecycle_stage,
            ])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $list = MarketingList::create($data);

        $this->audit->log('marketing.list.created', context: ['name' => $list->name], resourceType: 'marketing_list', resourceId: (string) $list->id, organizationId: $list->organization_id);

        return back()->with('status', __('List created.'));
    }

    public function update(Request $request, MarketingList $list): RedirectResponse
    {
        $list->update($this->validateData($request));
        $this->audit->log('marketing.list.updated', resourceType: 'marketing_list', resourceId: (string) $list->id, organizationId: $list->organization_id);

        return back()->with('status', __('List updated.'));
    }

    public function destroy(MarketingList $list): RedirectResponse
    {
        $this->audit->log('marketing.list.deleted', context: ['name' => $list->name], resourceType: 'marketing_list', resourceId: (string) $list->id, organizationId: $list->organization_id);
        $list->delete();

        return redirect()->route('marketing.lists.index')->with('status', __('List deleted.'));
    }

    public function addContact(Request $request, MarketingList $list): RedirectResponse
    {
        $data = $request->validate([
            'contact_id' => ['required', Rule::exists('contacts', 'id')->whereNull('deleted_at')],
        ]);

        $contact = Contact::findOrFail($data['contact_id']);
        $this->lists->addContact($list, $contact);

        return back()->with('status', __('Contact added to list.'));
    }

    public function removeContact(MarketingList $list, Contact $contact): RedirectResponse
    {
        $this->lists->removeContact($list, $contact);

        return back()->with('status', __('Contact removed from list.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validateData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => ['required', Rule::in(['static', 'dynamic'])],
            'criteria' => ['nullable', 'array'],
            'criteria.lifecycle_stage' => ['nullable', 'string', 'max:40'],
            'criteria.lead_source' => ['nullable', 'string', 'max:60'],
            'criteria.min_lead_score' => ['nullable', 'integer', 'min:0'],
        ]);
    }
}
