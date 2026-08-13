<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

/**
 * Base for API v1 controllers. Centralizes the response envelope (API-005):
 * single resources return `{data}`, collections return `{data, meta}`. Errors
 * are produced by the framework's exception handler as `{message, errors}`;
 * every response carries the X-Request-Id header from AssignRequestId.
 */
abstract class ApiController extends Controller
{
    /**
     * @param  array<string, mixed>  $data
     */
    protected function item(array $data, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data], $status);
    }

    /**
     * Wrap a paginator whose items have already been transformed to arrays.
     *
     * @param  LengthAwarePaginator<int, mixed>  $paginator
     */
    protected function collection(LengthAwarePaginator $paginator): JsonResponse
    {
        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }
}
