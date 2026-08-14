<?php

namespace App\Http\Controllers\Content;

use App\Http\Controllers\Controller;
use App\Models\AuthorityAsset;
use App\Models\Review;
use App\Models\ReviewRequest;
use App\Services\Content\ReputationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ReputationController extends Controller
{
    public function __construct(private ReputationService $reputation) {}

    public function index(): Response
    {
        return Inertia::render('content/reputation/index', [
            'aggregate' => $this->reputation->aggregate(),
            'reviews' => Review::latest('id')->limit(100)->get()->map(fn (Review $r) => [
                'id' => $r->id,
                'source' => $r->source,
                'author_name' => $r->author_name,
                'rating' => $r->rating,
                'body' => $r->body,
                'sentiment' => $r->sentiment,
                'responded' => $r->responded,
                'response' => $r->response,
            ]),
            'requests' => ReviewRequest::latest('id')->limit(50)->get()->map(fn (ReviewRequest $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'channel' => $r->channel,
                'status' => $r->status,
            ]),
            'assets' => AuthorityAsset::latest('id')->get()->map(fn (AuthorityAsset $a) => [
                'id' => $a->id,
                'type' => $a->type,
                'name' => $a->name,
                'issuer' => $a->issuer,
                'url' => $a->url,
            ]),
        ]);
    }

    public function storeReview(Request $request): RedirectResponse
    {
        $this->reputation->recordReview($request->validate([
            'source' => ['required', Rule::in(['google', 'clutch', 'g2', 'facebook', 'manual'])],
            'author_name' => ['nullable', 'string', 'max:120'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'body' => ['nullable', 'string', 'max:5000'],
            'url' => ['nullable', 'url', 'max:2048'],
        ]));

        return back()->with('status', __('Review recorded.'));
    }

    public function respond(Request $request, Review $review): RedirectResponse
    {
        $data = $request->validate(['response' => ['required', 'string', 'max:5000']]);
        $this->reputation->respond($review, $data['response']);

        return back()->with('status', __('Response saved.'));
    }

    public function import(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'source' => ['required', Rule::in(['google', 'clutch'])],
            'identifier' => ['required', 'string', 'max:200'],
        ]);

        $count = $this->reputation->import($data['source'], $data['identifier']);

        return back()->with('status', __('Imported :n reviews.', ['n' => $count]));
    }

    public function storeRequest(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'contact_id' => ['nullable', Rule::exists('contacts', 'id')],
            'channel' => ['required', Rule::in(['email', 'sms'])],
        ]);

        ReviewRequest::create($data);

        return back()->with('status', __('Review request created.'));
    }

    public function sendRequest(ReviewRequest $reviewRequest): RedirectResponse
    {
        $this->reputation->sendRequest($reviewRequest);

        return back()->with('status', __('Review request sent.'));
    }

    public function storeAsset(Request $request): RedirectResponse
    {
        AuthorityAsset::create($request->validate([
            'type' => ['required', Rule::in(['award', 'certification', 'logo', 'mention', 'proof'])],
            'name' => ['required', 'string', 'max:200'],
            'issuer' => ['nullable', 'string', 'max:200'],
            'url' => ['nullable', 'url', 'max:2048'],
            'image_url' => ['nullable', 'url', 'max:2048'],
            'achieved_on' => ['nullable', 'date'],
        ]));

        return back()->with('status', __('Authority asset added.'));
    }

    public function destroyAsset(AuthorityAsset $asset): RedirectResponse
    {
        $asset->delete();

        return back()->with('status', __('Asset removed.'));
    }
}
