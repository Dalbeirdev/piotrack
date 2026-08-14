<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $companies = Company::withCount('contacts', 'deals')
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->whereLike('name', "%{$s}%")->orWhereLike('domain', "%{$s}%"))
            ->latest('id')
            ->paginate($filters['per_page'] ?? 25)
            ->withQueryString()
            ->through(fn (Company $c) => $this->transform($c));

        return $this->collection($companies);
    }

    public function show(Company $company): JsonResponse
    {
        $company->loadCount('contacts', 'deals');

        return $this->item($this->transform($company));
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(Company $company): array
    {
        return [
            'id' => $company->id,
            'name' => $company->name,
            'domain' => $company->domain,
            'industry' => $company->industry,
            'size' => $company->size,
            'phone' => $company->phone,
            'website' => $company->website,
            'contacts_count' => $company->contacts_count,
            'deals_count' => $company->deals_count,
            'created_at' => $company->created_at?->toIso8601String(),
        ];
    }
}
