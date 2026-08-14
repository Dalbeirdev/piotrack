<?php

namespace App\Services\Content;

use App\Models\ContentPiece;
use App\Support\AuditLogger;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Content hub + editorial workflow (CONT). Manages content pieces and their
 * status transitions along the editorial flow, keeping the optimization score
 * fresh and stamping publish time.
 */
class ContentService
{
    /** Allowed forward/back transitions in the editorial workflow. */
    private const FLOW = [
        'idea' => ['draft'],
        'draft' => ['in_review', 'idea'],
        'in_review' => ['approved', 'draft'],
        'approved' => ['published', 'in_review'],
        'published' => ['archived'],
        'archived' => ['draft'],
    ];

    public function __construct(
        private OptimizationScorer $scorer,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ContentPiece
    {
        if (empty($data['slug'])) {
            $data['slug'] = $this->uniqueSlug((string) ($data['title'] ?? 'content'));
        }

        $data['status'] ??= 'idea';

        $piece = new ContentPiece($data);
        $piece->optimization_score = $this->scorer->score($piece);
        $piece->save();

        $this->audit->log('content.piece.created', context: ['title' => $piece->title, 'type' => $piece->content_type], resourceType: 'content_piece', resourceId: (string) $piece->id, organizationId: $piece->organization_id);

        return $piece;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ContentPiece $piece, array $data): ContentPiece
    {
        $piece->fill($data);
        $piece->optimization_score = $this->scorer->score($piece);
        $piece->save();

        return $piece;
    }

    public function transition(ContentPiece $piece, string $to): ContentPiece
    {
        $allowed = self::FLOW[$piece->status] ?? [];

        if (! in_array($to, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => __('Cannot move a :from piece to :to.', ['from' => $piece->status, 'to' => $to]),
            ]);
        }

        if ($to === 'published' && empty($piece->body)) {
            throw ValidationException::withMessages(['status' => __('Add a body before publishing.')]);
        }

        $piece->status = $to;
        if ($to === 'published') {
            $piece->published_at = now();
        }
        $piece->save();

        $this->audit->log('content.piece.status_changed', context: ['status' => $to], resourceType: 'content_piece', resourceId: (string) $piece->id, organizationId: $piece->organization_id);

        return $piece;
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'content';
        $slug = $base;
        $i = 1;

        while (ContentPiece::withoutGlobalScope('tenant')->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
