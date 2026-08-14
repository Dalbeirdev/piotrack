<?php

namespace App\Content\Providers;

use App\Content\Contracts\ReviewProvider;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Real review driver (Google Places / Clutch). Real code, untested here (no API
 * key) — status "Implemented (untested — requires credentials)" (ADR-0007, §38).
 * Selected with CONTENT_REVIEW_PROVIDER=live.
 */
class LiveReviewProvider implements ReviewProvider
{
    public function fetch(string $source, string $identifier): array
    {
        $key = (string) config('content.google.api_key', '');

        if ($source !== 'google' || $key === '') {
            return [];
        }

        try {
            $response = Http::timeout(30)->get('https://maps.googleapis.com/maps/api/place/details/json', [
                'place_id' => $identifier,
                'fields' => 'reviews',
                'key' => $key,
            ]);

            if ($response->failed()) {
                return [];
            }

            $reviews = [];
            foreach ((array) $response->json('result.reviews', []) as $review) {
                $reviews[] = [
                    'author_name' => (string) ($review['author_name'] ?? 'Anonymous'),
                    'rating' => (int) ($review['rating'] ?? 0),
                    'body' => (string) ($review['text'] ?? ''),
                    'url' => $review['author_url'] ?? null,
                    'reviewed_at' => date('Y-m-d H:i:s', (int) ($review['time'] ?? time())),
                ];
            }

            return $reviews;
        } catch (Throwable) {
            return [];
        }
    }
}
