<?php

namespace App\Services\Content;

use App\Content\Contracts\ReviewProvider;
use App\Models\Review;
use App\Models\ReviewRequest;
use App\Support\AuditLogger;

/**
 * Reputation management (REP): reviews, review-acquisition requests, and rating
 * + sentiment aggregation. Sentiment is derived from the rating (in-house);
 * review sync goes through the ReviewProvider (fixture tested).
 */
class ReputationService
{
    public function __construct(
        private ReviewProvider $provider,
        private AuditLogger $audit,
    ) {}

    public function sentiment(int $rating): string
    {
        return $rating >= 4 ? 'positive' : ($rating === 3 ? 'neutral' : 'negative');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function recordReview(array $data): Review
    {
        $data['sentiment'] = $this->sentiment((int) $data['rating']);
        $review = Review::create($data);

        $this->audit->log('content.review.recorded', context: ['source' => $review->source, 'rating' => $review->rating], resourceType: 'review', resourceId: (string) $review->id, organizationId: $review->organization_id);

        return $review;
    }

    public function respond(Review $review, string $response): Review
    {
        $review->update(['responded' => true, 'response' => $response]);
        $this->audit->log('content.review.responded', resourceType: 'review', resourceId: (string) $review->id, organizationId: $review->organization_id);

        return $review;
    }

    public function sendRequest(ReviewRequest $request): ReviewRequest
    {
        $request->update(['status' => 'sent', 'sent_at' => now()]);
        $this->audit->log('content.review_request.sent', context: ['channel' => $request->channel], resourceType: 'review_request', resourceId: (string) $request->id, organizationId: $request->organization_id);

        return $request;
    }

    /**
     * Import reviews for a source profile via the provider.
     */
    public function import(string $source, string $identifier): int
    {
        $rows = $this->provider->fetch($source, $identifier);

        foreach ($rows as $row) {
            Review::create([
                // `source` is the platform the review is filed under; `provider`
                // is the driver that produced it. The fixture driver invents an
                // author, rating and body, which must never read as a real
                // customer's words filed under Google.
                'source' => $source,
                'provider' => (string) config('content.review_provider', 'fixture'),
                'author_name' => $row['author_name'],
                'rating' => $row['rating'],
                'body' => $row['body'],
                'url' => $row['url'],
                'sentiment' => $this->sentiment($row['rating']),
                'reviewed_at' => $row['reviewed_at'],
            ]);
        }

        return count($rows);
    }

    /**
     * @return array{count: int, average: float, by_sentiment: array<string, int>, by_source: array<string, int>}
     */
    public function aggregate(): array
    {
        $reviews = Review::all();

        return [
            'count' => $reviews->count(),
            'average' => round((float) ($reviews->avg('rating') ?? 0), 2),
            'by_sentiment' => [
                'positive' => $reviews->where('sentiment', 'positive')->count(),
                'neutral' => $reviews->where('sentiment', 'neutral')->count(),
                'negative' => $reviews->where('sentiment', 'negative')->count(),
            ],
            'by_source' => $reviews->groupBy('source')->map->count()->all(),
        ];
    }
}
