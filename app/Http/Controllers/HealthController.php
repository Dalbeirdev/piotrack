<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
            'queue' => $this->passes(function (): bool {
                // Reaching both tables (throws if unavailable) proves the queue
                // backend is wired; passes() converts any failure to false.
                DB::table('jobs')->count();
                DB::table('failed_jobs')->count();

                return true;
            }),
            'storage' => $this->passes(function (): bool {
                $probe = 'health/'.Str::random(8).'.txt';
                Storage::disk('local')->put($probe, 'ok');
                $ok = Storage::disk('local')->get($probe) === 'ok';
                Storage::disk('local')->delete($probe);

                return $ok;
            }),
        ];

        $healthy = ! in_array(false, $checks, true);

        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'checks' => $checks,
            'metrics' => [
                'queue_pending' => $this->safeCount('jobs'),
                'queue_failed' => $this->safeCount('failed_jobs'),
            ],
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

    private function safeCount(string $table): int
    {
        try {
            return (int) DB::table($table)->count();
        } catch (Throwable) {
            return -1;
        }
    }
}
