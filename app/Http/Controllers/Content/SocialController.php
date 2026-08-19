<?php

namespace App\Http\Controllers\Content;

use App\Http\Controllers\Controller;
use App\Models\ContentPiece;
use App\Models\SocialPost;
use App\Services\Content\SocialService;
use App\Support\AuditLogger;
use App\Validation\TenantExists;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SocialController extends Controller
{
    private const CHANNELS = ['linkedin', 'facebook', 'x', 'youtube', 'other'];

    public function __construct(
        private SocialService $social,
        private AuditLogger $audit,
    ) {}

    public function index(): Response
    {
        return Inertia::render('content/social/index', [
            'posts' => SocialPost::latest('id')->get()->map(fn (SocialPost $p) => [
                'id' => $p->id,
                'channel' => $p->channel,
                'type' => $p->type,
                'body' => $p->body,
                'status' => $p->status,
                'scheduled_at' => $p->scheduled_at?->toIso8601String(),
                'published_at' => $p->published_at?->toIso8601String(),
                'impressions' => $p->impressions,
                'likes' => $p->likes,
                'comments' => $p->comments,
                'shares' => $p->shares,
            ]),
            'channels' => self::CHANNELS,
            'pieces' => ContentPiece::orderBy('title')->get(['id', 'title'])
                ->map(fn ($p) => ['id' => $p->id, 'title' => $p->title]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $post = SocialPost::create($request->validate([
            'channel' => ['required', Rule::in(self::CHANNELS)],
            'type' => ['nullable', 'string', 'max:40'],
            'body' => ['required', 'string', 'max:5000'],
            'media_url' => ['nullable', 'url', 'max:2048'],
            'content_piece_id' => ['nullable', TenantExists::in('content_pieces')],
        ]));

        $this->audit->log('content.social.created', context: ['channel' => $post->channel], resourceType: 'social_post', resourceId: (string) $post->id, organizationId: $post->organization_id);

        return back()->with('status', __('Post created.'));
    }

    public function schedule(Request $request, SocialPost $post): RedirectResponse
    {
        $data = $request->validate(['scheduled_at' => ['required', 'date']]);
        $this->social->schedule($post, Carbon::parse($data['scheduled_at']));

        return back()->with('status', __('Post scheduled.'));
    }

    public function publish(SocialPost $post): RedirectResponse
    {
        $this->social->publish($post);

        return back()->with('status', __('Post published.'));
    }

    public function refreshMetrics(SocialPost $post): RedirectResponse
    {
        $this->social->refreshMetrics($post);

        return back()->with('status', __('Metrics refreshed.'));
    }

    public function destroy(SocialPost $post): RedirectResponse
    {
        $post->delete();

        return back()->with('status', __('Post deleted.'));
    }
}
