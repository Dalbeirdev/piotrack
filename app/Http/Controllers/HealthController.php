<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    /**
     * Application health endpoint for monitors and deployment checks.
     * The framework's bare liveness endpoint is /up; this one verifies
     * critical dependencies and reports per-component status.
     */
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->passes(fn () => DB::select('select 1') !== []),
            'cache' => $this->passes(function (): bool {
                Cache::put('health-check-probe', 'ok', 10);

                return Cache::get('health-check-probe') === 'ok';
            }),
        ];

        $healthy = ! in_array(false, $checks, true);

        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'checks' => $checks,
            'version' => config('app.version'),
        ], $healthy ? 200 : 503);
    }

    /**
     * @param  callable(): bool  $probe
     */
    private function passes(callable $probe): bool
    {
        try {
            return $probe();
        } catch (Throwable) {
            return false;
        }
    }
}
