<?php

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use App\Models\SeoAudit;
use App\Services\Seo\TechnicalSeoAuditor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditController extends Controller
{
    public function __construct(private TechnicalSeoAuditor $auditor) {}

    public function index(): Response
    {
        return Inertia::render('seo/audits/index', [
            'audits' => SeoAudit::latest('id')->limit(50)->get()->map(fn (SeoAudit $a) => [
                'id' => $a->id,
                'url' => $a->url,
                'score' => $a->score,
                'issues_count' => $a->issues_count,
                'created_at' => $a->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['url' => ['required', 'url', 'max:2048']]);

        $audit = $this->auditor->crawl($data['url']);

        return redirect()->route('seo.audits.show', $audit->id)->with('status', __('Audit complete.'));
    }

    public function show(SeoAudit $audit): Response
    {
        return Inertia::render('seo/audits/show', [
            'audit' => [
                'id' => $audit->id,
                'url' => $audit->url,
                'score' => $audit->score,
                'issues_count' => $audit->issues_count,
                'fetched_status' => $audit->fetched_status,
                'checks' => $audit->checks ?? [],
                'created_at' => $audit->created_at?->toIso8601String(),
            ],
        ]);
    }
}
