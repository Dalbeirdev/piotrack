<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\LandingPage;
use App\Support\CurrentOrganization;
use Illuminate\View\View;

/**
 * Public, unauthenticated landing-page rendering. Resolves the tenant from the
 * page slug and increments its view count.
 */
class PublicLandingPageController extends Controller
{
    public function __construct(private CurrentOrganization $currentOrganization) {}

    public function show(string $slug): View
    {
        $page = LandingPage::withoutGlobalScope('tenant')
            ->where('slug', $slug)->where('status', 'published')->first();

        abort_if($page === null, 404);

        $organization = $page->organization()->first();
        abort_if($organization === null, 404);

        $this->currentOrganization->set($organization);

        LandingPage::withoutGlobalScope('tenant')->whereKey($page->id)->increment('view_count');

        $form = $page->form_id !== null ? Form::find($page->form_id) : null;

        return view('public.landing', ['page' => $page, 'form' => $form]);
    }
}
