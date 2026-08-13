<?php

namespace App\Services\Marketing;

use App\Models\Contact;
use App\Models\Funnel;

/**
 * Funnel reporting (FUNL-020…024). A funnel is a named, ordered set of stages
 * mapped to contact lifecycle stages; counts come from the contacts currently
 * in each mapped lifecycle stage within the tenant.
 */
class FunnelService
{
    /**
     * @return list<array{id: int, name: string, category: string, count: int}>
     */
    public function stageCounts(Funnel $funnel): array
    {
        return $funnel->stages()->get()->map(fn ($stage) => [
            'id' => $stage->id,
            'name' => $stage->name,
            'category' => $stage->category,
            'count' => $stage->lifecycle_stage !== null
                ? Contact::where('lifecycle_stage', $stage->lifecycle_stage)->count()
                : 0,
        ])->all();
    }
}
