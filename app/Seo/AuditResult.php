<?php

namespace App\Seo;

/**
 * The outcome of a technical on-page audit: a weighted score (0–100), the list
 * of individual checks, and the count of non-passing checks.
 */
final class AuditResult
{
    /**
     * @param  list<array{key: string, label: string, status: string, detail: string, weight: int}>  $checks
     */
    public function __construct(
        public int $score,
        public array $checks,
        public int $issuesCount,
    ) {}

    /**
     * Checks as stored/serialized (without the internal weight).
     *
     * @return list<array{key: string, label: string, status: string, detail: string}>
     */
    public function checksForStorage(): array
    {
        return array_map(
            fn (array $c) => ['key' => $c['key'], 'label' => $c['label'], 'status' => $c['status'], 'detail' => $c['detail']],
            $this->checks,
        );
    }
}
