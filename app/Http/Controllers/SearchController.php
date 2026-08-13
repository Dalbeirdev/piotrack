<?php

namespace App\Http\Controllers;

use App\Services\GlobalSearch;
use App\Support\CurrentOrganization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __construct(
        private GlobalSearch $search,
        private CurrentOrganization $currentOrganization,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $term = (string) $request->query('q', '');

        return response()->json([
            'groups' => $this->search->search(
                $request->user(),
                $this->currentOrganization->get(),
                $term,
            ),
        ]);
    }
}
