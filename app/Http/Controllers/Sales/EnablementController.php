<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\SalesAsset;
use App\Models\SalesPlay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EnablementController extends Controller
{
    private const TYPES = ['deck', 'one_pager', 'battlecard', 'script', 'email_template', 'roi_calculator', 'proof', 'persona'];

    public function index(): Response
    {
        return Inertia::render('sales/enablement/index', [
            'assets' => SalesAsset::latest('id')->get()->map(fn (SalesAsset $a) => [
                'id' => $a->id,
                'type' => $a->type,
                'title' => $a->title,
                'description' => $a->description,
                'url' => $a->url,
            ]),
            'plays' => SalesPlay::latest('id')->get()->map(fn (SalesPlay $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'description' => $p->description,
                'target_segment' => $p->target_segment,
                'steps' => $p->steps ?? [],
            ]),
            'types' => self::TYPES,
        ]);
    }

    public function storeAsset(Request $request): RedirectResponse
    {
        SalesAsset::create($request->validate([
            'type' => ['required', Rule::in(self::TYPES)],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:1000'],
            'content' => ['nullable', 'string'],
            'url' => ['nullable', 'url', 'max:2048'],
        ]));

        return back()->with('status', __('Asset added.'));
    }

    public function destroyAsset(SalesAsset $asset): RedirectResponse
    {
        $asset->delete();

        return back()->with('status', __('Asset removed.'));
    }

    public function storePlay(Request $request): RedirectResponse
    {
        SalesPlay::create($request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'target_segment' => ['nullable', 'string', 'max:120'],
            'steps' => ['nullable', 'array'],
            'steps.*.title' => ['required', 'string', 'max:200'],
        ]));

        return back()->with('status', __('Play created.'));
    }

    public function destroyPlay(SalesPlay $play): RedirectResponse
    {
        $play->delete();

        return back()->with('status', __('Play removed.'));
    }
}
