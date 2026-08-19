<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\LandingPage;
use App\Support\AuditLogger;
use App\Validation\TenantExists;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class LandingPageController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(): Response
    {
        return Inertia::render('marketing/landing-pages/index', [
            'pages' => LandingPage::latest('id')->get()->map(fn (LandingPage $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'headline' => $p->headline,
                'status' => $p->status,
                'view_count' => $p->view_count,
                'public_url' => url("/p/{$p->slug}"),
            ]),
            'forms' => Form::orderBy('name')->get(['id', 'name'])
                ->map(fn ($f) => ['id' => $f->id, 'name' => $f->name]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $data['slug'] = $this->uniqueSlug($data['name']);
        $page = LandingPage::create($data);

        $this->audit->log('marketing.landing_page.created', context: ['name' => $page->name], resourceType: 'landing_page', resourceId: (string) $page->id, organizationId: $page->organization_id);

        return back()->with('status', __('Landing page created.'));
    }

    public function update(Request $request, LandingPage $landingPage): RedirectResponse
    {
        $landingPage->update($this->validateData($request));
        $this->audit->log('marketing.landing_page.updated', resourceType: 'landing_page', resourceId: (string) $landingPage->id, organizationId: $landingPage->organization_id);

        return back()->with('status', __('Landing page updated.'));
    }

    public function publish(LandingPage $landingPage): RedirectResponse
    {
        $landingPage->update(['status' => $landingPage->isPublished() ? 'draft' : 'published']);

        return back()->with('status', __('Landing page :status.', ['status' => $landingPage->status]));
    }

    public function destroy(LandingPage $landingPage): RedirectResponse
    {
        $this->audit->log('marketing.landing_page.deleted', context: ['name' => $landingPage->name], resourceType: 'landing_page', resourceId: (string) $landingPage->id, organizationId: $landingPage->organization_id);
        $landingPage->delete();

        return redirect()->route('marketing.landing-pages.index')->with('status', __('Landing page deleted.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validateData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'headline' => ['required', 'string', 'max:200'],
            'subheadline' => ['nullable', 'string', 'max:300'],
            'body_html' => ['nullable', 'string', 'max:20000'],
            'form_id' => ['nullable', TenantExists::in('forms')],
        ]);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'page';
        $slug = $base;
        $i = 1;

        while (LandingPage::withoutGlobalScope('tenant')->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
