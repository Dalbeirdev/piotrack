<?php

namespace App\Services\Seo;

use App\Models\Keyword;
use Illuminate\Database\Eloquent\Collection;

/**
 * Keyword inventory operations (KSEO-013/014/015): clustering by primary topic
 * token, keyword→page mapping, and content-gap (keywords with no mapped page).
 */
class KeywordService
{
    /** Generic terms stripped before choosing a cluster token. */
    private const STOPWORDS = ['the', 'a', 'an', 'for', 'of', 'in', 'to', 'and', 'best', 'top', 'msp', 'it', 'services', 'service', 'company', 'near', 'me'];

    /**
     * Assign each tracked keyword a cluster derived from its primary topic token.
     */
    public function recluster(): int
    {
        $keywords = Keyword::all();

        foreach ($keywords as $keyword) {
            $keyword->update(['cluster' => $this->primaryToken($keyword->phrase)]);
        }

        return $keywords->count();
    }

    /**
     * Keywords with no mapped page — the content gap (KSEO-015).
     *
     * @return Collection<int, Keyword>
     */
    public function contentGap(): Collection
    {
        return Keyword::whereNull('mapped_url')->orderBy('phrase')->get();
    }

    public function primaryToken(string $phrase): string
    {
        $tokens = preg_split('/\s+/', mb_strtolower(trim($phrase))) ?: [];
        $tokens = array_values(array_filter(
            $tokens,
            fn (string $t) => ! in_array($t, self::STOPWORDS, true) && mb_strlen($t) > 2,
        ));

        return $tokens[0] ?? 'general';
    }
}
