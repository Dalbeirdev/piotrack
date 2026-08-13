<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Optional request de-duplication for unsafe API methods (API-004). When a
 * client sends an `Idempotency-Key` on a POST/PUT/PATCH/DELETE, the first
 * successful response is cached and replayed for any repeat with the same key,
 * so a retried create does not produce a duplicate. Safe methods pass straight
 * through, and requests without the header are unaffected.
 *
 * The key is scoped to caller + organization + method + path so a value can
 * never be replayed across tenants or endpoints.
 */
class Idempotency
{
    private const TTL_HOURS = 24;

    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        $key = $request->header('Idempotency-Key');

        if ($key === null || $key === '') {
            return $next($request);
        }

        $cacheKey = 'idem:'.hash('sha256', implode('|', [
            $request->user()?->getAuthIdentifier(),
            $request->header('X-Organization-Id', ''),
            $request->method(),
            $request->path(),
            $key,
        ]));

        /** @var array{status: int, body: mixed}|null $cached */
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return response()->json($cached['body'], $cached['status'])
                ->header('Idempotent-Replayed', 'true');
        }

        $response = $next($request);

        // Only cache deterministic successful JSON responses; never store 5xx.
        if ($response instanceof JsonResponse && $response->getStatusCode() < 500) {
            Cache::put($cacheKey, [
                'status' => $response->getStatusCode(),
                'body' => $response->getData(true),
            ], now()->addHours(self::TTL_HOURS));
        }

        return $response;
    }
}
