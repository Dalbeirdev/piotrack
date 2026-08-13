<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Contact;
use App\Support\AuditLogger;
use App\Support\CurrentOrganization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ContactController extends ApiController
{
    public function __construct(
        private CurrentOrganization $currentOrganization,
        private AuditLogger $audit,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $contacts = Contact::with('company:id,name', 'owner:id,name')
            ->search($filters['search'] ?? null)
            ->latest('id')
            ->paginate($filters['per_page'] ?? 25)
            ->withQueryString()
            ->through(fn (Contact $c) => $this->transform($c));

        return $this->collection($contacts);
    }

    public function show(Contact $contact): JsonResponse
    {
        $contact->load('company:id,name', 'owner:id,name');

        return $this->item($this->transform($contact));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'title' => ['nullable', 'string', 'max:120'],
            'company_id' => ['nullable', Rule::exists('companies', 'id')->where('organization_id', $this->currentOrganization->id())],
            'lead_source' => ['nullable', 'string', 'max:120'],
            'owner_id' => ['nullable', Rule::exists('organization_user', 'user_id')->where('organization_id', $this->currentOrganization->id())],
        ]);

        // Duplicate detection (CRM-026), mirrored from the web controller.
        if (! empty($data['email']) && Contact::where('email', $data['email'])->exists()) {
            throw ValidationException::withMessages(['email' => __('A contact with this email already exists.')]);
        }

        $data['owner_id'] ??= $request->user()->getAuthIdentifier();
        $contact = Contact::create($data);

        $this->audit->log('crm.contact.created', context: ['name' => $contact->fullName(), 'via' => 'api'], resourceType: 'contact', resourceId: (string) $contact->id, organizationId: $contact->organization_id);

        $contact->load('company:id,name', 'owner:id,name');

        return $this->item($this->transform($contact), 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(Contact $contact): array
    {
        return [
            'id' => $contact->id,
            'first_name' => $contact->first_name,
            'last_name' => $contact->last_name,
            'name' => $contact->fullName(),
            'email' => $contact->email,
            'phone' => $contact->phone,
            'title' => $contact->title,
            'lead_source' => $contact->lead_source,
            'company' => $contact->company !== null
                ? ['id' => $contact->company->id, 'name' => $contact->company->name]
                : null,
            'owner' => $contact->owner !== null
                ? ['id' => $contact->owner->id, 'name' => $contact->owner->name]
                : null,
            'created_at' => $contact->created_at?->toIso8601String(),
        ];
    }
}
