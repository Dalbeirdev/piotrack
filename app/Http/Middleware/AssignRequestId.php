<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AssignRequestId
{
    /**
     * Attach a request ID to every request: accepted from the client when it is
     * well-formed (so upstream systems can trace calls end-to-end), generated
     * otherwise. The ID is shared with all log entries written during the
     * request and returned in the X-Request-Id response header.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = (string) $request->headers->get('X-Request-Id', '');

        if (! preg_match('/^[A-Za-z0-9._\-]{8,64}$/', $requestId)) {
            $requestId = (string) Str::uuid();
        }

        $request->attributes->set('request_id', $requestId);
        Log::shareContext(['request_id' => $requestId]);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
