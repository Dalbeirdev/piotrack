<?php

namespace App\Http\Controllers\Advertising;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Models\AdCampaign;
use App\Models\AdGroup;
use App\Models\AdKeyword;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Ad group + nested ad + keyword management within a campaign (PPC/LIAD/META
 * structure, incl. negative keywords). All tenant-scoped via BelongsToTenant.
 */
class AdGroupController extends Controller
{
    public function storeGroup(Request $request, AdCampaign $campaign): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'bid_strategy' => ['nullable', Rule::in(['manual_cpc', 'maximize_conversions', 'target_cpa'])],
            'bid_amount' => ['nullable', 'integer', 'min:0'],
        ]);

        $campaign->groups()->create($data);

        return back()->with('status', __('Ad group added.'));
    }

    public function destroyGroup(AdGroup $group): RedirectResponse
    {
        $group->delete();

        return back()->with('status', __('Ad group removed.'));
    }

    public function storeAd(Request $request, AdGroup $group): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'headline' => ['nullable', 'string', 'max:200'],
            'body' => ['nullable', 'string', 'max:2000'],
            'cta' => ['nullable', 'string', 'max:60'],
            'destination_url' => ['nullable', 'url', 'max:2048'],
        ]);

        $group->ads()->create($data);

        return back()->with('status', __('Ad added.'));
    }

    public function destroyAd(Ad $ad): RedirectResponse
    {
        $ad->delete();

        return back()->with('status', __('Ad removed.'));
    }

    public function storeKeyword(Request $request, AdGroup $group): RedirectResponse
    {
        $data = $request->validate([
            'phrase' => ['required', 'string', 'max:200'],
            'match_type' => ['required', Rule::in(['broad', 'phrase', 'exact'])],
            'is_negative' => ['boolean'],
        ]);

        $group->keywords()->create($data);

        return back()->with('status', __('Keyword added.'));
    }

    public function destroyKeyword(AdKeyword $keyword): RedirectResponse
    {
        $keyword->delete();

        return back()->with('status', __('Keyword removed.'));
    }
}
