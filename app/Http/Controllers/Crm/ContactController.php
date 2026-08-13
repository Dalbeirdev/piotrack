<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Contact;
use App\Support\AuditLogger;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function __construct(
        private CurrentOrganization $currentOrganization,
        private AuditLogger $audit,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'owner' => ['nullable', 'integer'],
        ]);

        $contacts = Contact::with('company:id,name', 'owner:id,name')
            ->search($filters['search'] ?? null)
            ->when($filters['owner'] ?? null, fn ($q, $owner) => $q->where('owner_id', $owner))
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Contact $c) => [
                'id' => $c->id,
                'name' => $c->fullName(),
                'email' => $c->email,
                'title' => $c->title,
                'company' => $c->company?->name,
                'owner' => $c->owner?->name,
            ]);

        return Inertia::render('crm/contacts/index', [
            'contacts' => $contacts,
            'filters' => $filters,
            'owners' => $this->memberOptions(),
            'companies' => $this->companyOptions(),
        ]);
    }

    public function show(Contact $contact): Response
    {
        $contact->load('company:id,name', 'owner:id,name');

        return Inertia::render('crm/contacts/show', [
            'contact' => [
                'id' => $contact->id,
                'first_name' => $contact->first_name,
                'last_name' => $contact->last_name,
                'email' => $contact->email,
                'phone' => $contact->phone,
                'title' => $contact->title,
                'lead_source' => $contact->lead_source,
                'campaign' => $contact->campaign,
                'company' => $contact->company !== null ? ['id' => $contact->company->id, 'name' => $contact->company->name] : null,
                'owner' => $contact->owner?->name,
            ],
            'activities' => $this->timeline($contact),
            'deals' => $contact->deals()->get(['id', 'name', 'value', 'status'])->map(fn ($d) => [
                'id' => $d->id, 'name' => $d->name, 'value' => $d->value, 'status' => $d->status,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);

        // Duplicate detection (CRM-026): reject a contact whose email already
        // exists in this organization.
        if (! empty($data['email']) && Contact::where('email', $data['email'])->exists()) {
            return back()->withErrors(['email' => __('A contact with this email already exists.')]);
        }

        $data['owner_id'] ??= $request->user()->id;
        $contact = Contact::create($data);

        $this->audit->log('crm.contact.created', context: ['name' => $contact->fullName()], resourceType: 'contact', resourceId: (string) $contact->id, organizationId: $contact->organization_id);

        return back()->with('status', __('Contact created.'));
    }

    public function update(Request $request, Contact $contact): RedirectResponse
    {
        $data = $this->validateData($request);

        if (! empty($data['email']) && Contact::where('email', $data['email'])->where('id', '!=', $contact->id)->exists()) {
            return back()->withErrors(['email' => __('Another contact with this email already exists.')]);
        }

        $contact->update($data);
        $this->audit->log('crm.contact.updated', resourceType: 'contact', resourceId: (string) $contact->id, organizationId: $contact->organization_id);

        return back()->with('status', __('Contact updated.'));
    }

    public function destroy(Contact $contact): RedirectResponse
    {
        $this->audit->log('crm.contact.deleted', context: ['name' => $contact->fullName()], resourceType: 'contact', resourceId: (string) $contact->id, organizationId: $contact->organization_id);
        $contact->delete();

        return redirect()->route('crm.contacts.index')->with('status', __('Contact deleted.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validateData(Request $request): array
    {
        return $request->validate([
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'title' => ['nullable', 'string', 'max:120'],
            'company_id' => ['nullable', Rule::exists('companies', 'id')->where('organization_id', $this->currentOrganization->id())],
            'lead_source' => ['nullable', 'string', 'max:120'],
            'campaign' => ['nullable', 'string', 'max:120'],
            'owner_id' => ['nullable', Rule::exists('organization_user', 'user_id')->where('organization_id', $this->currentOrganization->id())],
        ]);
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function memberOptions(): array
    {
        return $this->currentOrganization->get()->members()
            ->orderBy('name')
            ->get(['users.id', 'users.name'])
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])
            ->all();
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function companyOptions(): array
    {
        return Company::orderBy('name')->limit(200)->get(['id', 'name'])
            ->map(fn (Company $c) => ['id' => $c->id, 'name' => $c->name])->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function timeline(Contact $contact): array
    {
        return $contact->activities()
            ->with('user:id,name')
            ->latest('id')
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'type' => $a->type,
                'title' => $a->title,
                'body' => $a->body,
                'due_at' => $a->due_at,
                'completed_at' => $a->completed_at,
                'user' => $a->user?->name,
                'created_at' => $a->created_at,
            ])->all();
    }
}
