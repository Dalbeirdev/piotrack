<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Deal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DealController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', 'string', 'in:open,won,lost'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $deals = Deal::with('company:id,name', 'stage:id,name', 'owner:id,name')
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->latest('id')
            ->paginate($filters['per_page'] ?? 25)
            ->withQueryString()
            ->through(fn (Deal $d) => $this->transform($d));

        return $this->collection($deals);
    }

    public function show(Deal $deal): JsonResponse
    {
        $deal->load('company:id,name', 'stage:id,name', 'owner:id,name', 'contact:id,first_name,last_name');

        return $this->item($this->transform($deal));
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(Deal $deal): array
    {
        return [
            'id' => $deal->id,
            'name' => $deal->name,
            'value' => $deal->value,
            'currency' => $deal->currency,
            'status' => $deal->status,
            'stage' => $deal->stage?->name,
            'company' => $deal->company !== null
                ? ['id' => $deal->company->id, 'name' => $deal->company->name]
                : null,
            'owner' => $deal->owner !== null
                ? ['id' => $deal->owner->id, 'name' => $deal->owner->name]
                : null,
            'expected_close_date' => $deal->expected_close_date?->toDateString(),
            'created_at' => $deal->created_at?->toIso8601String(),
        ];
    }
}
