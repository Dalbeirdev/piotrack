<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Funnel;
use App\Services\Marketing\FunnelService;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FunnelController extends Controller
{
    public function __construct(
        private FunnelService $funnels,
        private AuditLogger $audit,
    ) {}

    public function index(): Response
    {
        return Inertia::render('marketing/funnels/index', [
            'funnels' => Funnel::with('stages')->latest('id')->get()->map(fn (Funnel $f) => [
                'id' => $f->id,
                'name' => $f->name,
                'description' => $f->description,
                'stages' => $this->funnels->stageCounts($f),
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'stages' => ['nullable', 'array'],
            'stages.*.name' => ['required', 'string', 'max:120'],
            'stages.*.category' => ['required', 'string', 'max:10'],
            'stages.*.lifecycle_stage' => ['nullable', 'string', 'max:40'],
        ]);

        $funnel = Funnel::create(['name' => $data['name'], 'description' => $data['description'] ?? null]);

        foreach ($data['stages'] ?? [] as $i => $stage) {
            $funnel->stages()->create([
                'name' => $stage['name'],
                'position' => $i + 1,
                'category' => $stage['category'],
                'lifecycle_stage' => $stage['lifecycle_stage'] ?? null,
            ]);
        }

        $this->audit->log('marketing.funnel.created', context: ['name' => $funnel->name], resourceType: 'funnel', resourceId: (string) $funnel->id, organizationId: $funnel->organization_id);

        return back()->with('status', __('Funnel created.'));
    }

    public function destroy(Funnel $funnel): RedirectResponse
    {
        $this->audit->log('marketing.funnel.deleted', context: ['name' => $funnel->name], resourceType: 'funnel', resourceId: (string) $funnel->id, organizationId: $funnel->organization_id);
        $funnel->delete();

        return back()->with('status', __('Funnel deleted.'));
    }
}
