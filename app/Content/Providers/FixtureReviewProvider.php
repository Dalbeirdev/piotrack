<?php

namespace App\Content\Providers;

use App\Content\Contracts\ReviewProvider;
use Illuminate\Support\Carbon;

/**
 * The default, fully-tested review driver: a deterministic set of reviews for a
 * source profile. The import + rating/sentiment aggregation pipeline runs for
 * real against it (ADR-0007).
 */
class FixtureReviewProvider implements ReviewProvider
{
    public function fetch(string $source, string $identifier): array
    {
        $seed = crc32($source.'|'.$identifier);
        $reviews = [];

        for ($i = 0; $i < 3; $i++) {
            $rating = 3 + (($seed >> $i) % 3); // 3–5

            $reviews[] = [
                'author_name' => 'Reviewer '.($i + 1),
                'rating' => $rating,
                'body' => 'Reliable managed IT support with responsive service.',
                'url' => null,
                'reviewed_at' => Carbon::now()->subDays($i * 3)->toDateTimeString(),
            ];
        }

        return $reviews;
    }
}
