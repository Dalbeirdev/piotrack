<?php

namespace App\Services\Analytics;

use App\Models\AdMetric;
use App\Models\AiVisibilityCheck;
use App\Models\Contact;
use App\Models\ContentPiece;
use App\Models\Keyword;
use App\Models\OutboundMessage;
use App\Models\RetargetingAudience;
use App\Models\SocialPost;

/**
 * Omnichannel marketing view (OMNI). A unified, per-channel performance rollup
 * across every acquisition surface the platform already runs, plus the unified
 * prospect journey (one contact's cross-channel touchpoints + lifecycle). A
 * channel with no data is reported inactive with a zero metric — never faked.
 */
class OmnichannelService
{
    public function __construct(private AttributionService $attribution) {}

    /**
     * Per-channel summary: each row has a stable key, a label, an active flag and
     * one primary metric drawn from real rows.
     *
     * @return list<array{channel: string, label: string, active: bool, metric: string, value: int}>
     */
    public function channels(): array
    {
        $seoTracked = Keyword::where('is_tracked', true)->count();
        $adClicks = (int) AdMetric::sum('clicks');
        $emailSent = OutboundMessage::where('channel', 'email')->whereNotNull('sent_at')->count();
        $smsSent = OutboundMessage::where('channel', 'sms')->whereNotNull('sent_at')->count();
        $contentPublished = ContentPiece::where('status', 'published')->count();
        $socialImpressions = (int) SocialPost::sum('impressions');
        $retargeting = (int) RetargetingAudience::sum('member_count');
        $aiMentions = AiVisibilityCheck::where('mentioned', true)->count();

        return [
            ['channel' => 'seo', 'label' => 'SEO', 'active' => $seoTracked > 0, 'metric' => 'tracked keywords', 'value' => $seoTracked],
            ['channel' => 'ads', 'label' => 'Paid Ads', 'active' => $adClicks > 0, 'metric' => 'clicks', 'value' => $adClicks],
            ['channel' => 'email', 'label' => 'Email', 'active' => $emailSent > 0, 'metric' => 'sent', 'value' => $emailSent],
            ['channel' => 'sms', 'label' => 'SMS', 'active' => $smsSent > 0, 'metric' => 'sent', 'value' => $smsSent],
            ['channel' => 'content', 'label' => 'Content', 'active' => $contentPublished > 0, 'metric' => 'published', 'value' => $contentPublished],
            ['channel' => 'social', 'label' => 'Social', 'active' => $socialImpressions > 0, 'metric' => 'impressions', 'value' => $socialImpressions],
            ['channel' => 'retargeting', 'label' => 'Retargeting', 'active' => $retargeting > 0, 'metric' => 'audience', 'value' => $retargeting],
            ['channel' => 'ai_search', 'label' => 'AI Search', 'active' => $aiMentions > 0, 'metric' => 'mentions', 'value' => $aiMentions],
        ];
    }

    /**
     * The unified prospect journey for one contact: chronological cross-channel
     * touchpoints + current lifecycle stage (OMNI-016/017/018).
     *
     * @return array<string, mixed>
     */
    public function journey(Contact $contact): array
    {
        return [
            'contact_id' => $contact->id,
            'lifecycle_stage' => $contact->lifecycle_stage,
            'lead_score' => $contact->lead_score,
            'first_touch' => $this->attribution->firstTouch($contact),
            'last_touch' => $this->attribution->lastTouch($contact),
            'touchpoints' => $this->attribution->touchpoints($contact),
        ];
    }
}
