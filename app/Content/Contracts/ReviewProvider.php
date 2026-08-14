<?php

namespace App\Content\Contracts;

/**
 * Review sync (ADR-0007). The `fixture` driver is the tested default; live
 * drivers (Google/Clutch) are real but untested here (no credentials).
 */
interface ReviewProvider
{
    /**
     * Fetch reviews for a source profile.
     *
     * @return list<array{author_name: string, rating: int, body: string, url: ?string, reviewed_at: string}>
     */
    public function fetch(string $source, string $identifier): array;
}
