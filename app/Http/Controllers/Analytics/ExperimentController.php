<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Models\Experiment;
use App\Models\ExperimentVariant;
use App\Services\Analytics\ExperimentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ExperimentController extends Controller
{
    private const TYPES = ['landing_page', 'cta', 'form', 'copy', 'headline', 'offer', 'layout', 'ux', 'multivariate'];

    public function __construct(private ExperimentService $experiments) {}

    public function index(): Response
    {
        return Inertia::render('analytics/experiments', [
            'experiments' => Experiment::with('variants')->latest('id')->get()->map(fn (Experiment $e) => [
                'id' => $e->id,
                'name' => $e->name,
                'type' => $e->type,
                'hypothesis' => $e->hypothesis,
                'status' => $e->status,
                'winning_variant_id' => $e->winning_variant_id,
                'results' => $this->experiments->results($e),
            ]),
            'types' => self::TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::in(self::TYPES)],
            'hypothesis' => ['nullable', 'string', 'max:1000'],
            'variants' => ['required', 'array', 'min:2'],
            'variants.*.name' => ['required', 'string', 'max:100'],
        ]);

        /** @var list<array{name: string, is_control?: bool}> $variants */
        $variants = $data['variants'];
        $this->experiments->create($data, $variants);

        return back()->with('status', __('Experiment created.'));
    }

    public function start(Experiment $experiment): RedirectResponse
    {
        $this->experiments->start($experiment);

        return back()->with('status', __('Experiment started.'));
    }

    public function record(Request $request, ExperimentVariant $variant): RedirectResponse
    {
        $data = $request->validate([
            'impressions' => ['required', 'integer', 'min:0'],
            'conversions' => ['required', 'integer', 'min:0'],
        ]);

        $this->experiments->record($variant, (int) $data['impressions'], (int) $data['conversions']);

        return back()->with('status', __('Results recorded.'));
    }

    public function conclude(Experiment $experiment): RedirectResponse
    {
        $this->experiments->conclude($experiment);

        return back()->with('status', __('Experiment concluded.'));
    }

    public function destroy(Experiment $experiment): RedirectResponse
    {
        $experiment->delete();

        return back()->with('status', __('Experiment removed.'));
    }
}
