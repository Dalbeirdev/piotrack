<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\MarketingList;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FormController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(): Response
    {
        return Inertia::render('marketing/forms/index', [
            'forms' => Form::latest('id')->get()->map(fn (Form $f) => [
                'id' => $f->id,
                'name' => $f->name,
                'slug' => $f->slug,
                'status' => $f->status,
                'submission_count' => $f->submission_count,
                'public_url' => url("/f/{$f->slug}"),
            ]),
            'lists' => MarketingList::orderBy('name')->get(['id', 'name'])
                ->map(fn ($l) => ['id' => $l->id, 'name' => $l->name]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $data['slug'] = $this->uniqueSlug($data['name']);
        $form = Form::create($data);

        $this->audit->log('marketing.form.created', context: ['name' => $form->name], resourceType: 'form', resourceId: (string) $form->id, organizationId: $form->organization_id);

        return back()->with('status', __('Form created.'));
    }

    public function update(Request $request, Form $form): RedirectResponse
    {
        $form->update($this->validateData($request));
        $this->audit->log('marketing.form.updated', resourceType: 'form', resourceId: (string) $form->id, organizationId: $form->organization_id);

        return back()->with('status', __('Form updated.'));
    }

    public function publish(Form $form): RedirectResponse
    {
        $form->update(['status' => $form->isPublished() ? 'draft' : 'published']);
        $this->audit->log('marketing.form.published', context: ['status' => $form->status], resourceType: 'form', resourceId: (string) $form->id, organizationId: $form->organization_id);

        return back()->with('status', __('Form :status.', ['status' => $form->status]));
    }

    public function destroy(Form $form): RedirectResponse
    {
        $this->audit->log('marketing.form.deleted', context: ['name' => $form->name], resourceType: 'form', resourceId: (string) $form->id, organizationId: $form->organization_id);
        $form->delete();

        return redirect()->route('marketing.forms.index')->with('status', __('Form deleted.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validateData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'fields' => ['required', 'array', 'min:1'],
            'fields.*.name' => ['required', 'string', 'max:60'],
            'fields.*.label' => ['required', 'string', 'max:120'],
            'fields.*.type' => ['required', Rule::in(['text', 'email', 'tel', 'textarea'])],
            'fields.*.required' => ['boolean'],
            'target_list_id' => ['nullable', Rule::exists('marketing_lists', 'id')],
            'lifecycle_stage' => ['nullable', 'string', 'max:40'],
            'settings' => ['nullable', 'array'],
            'settings.success_message' => ['nullable', 'string', 'max:500'],
            'settings.redirect_url' => ['nullable', 'url', 'max:500'],
        ]);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'form';
        $slug = $base;
        $i = 1;

        while (Form::withoutGlobalScope('tenant')->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
