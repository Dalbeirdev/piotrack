<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline security response headers (SEC-002).
 *
 * The CSP is deliberately conservative but Vite-compatible: Inertia ships a
 * small inline bootstrap and Vite injects inline styles, so `unsafe-inline` is
 * permitted for styles and, in local development only, for scripts. Framing is
 * denied outright (the product has no embeddable surface), and the browser is
 * told not to sniff content types or leak full referrers cross-origin.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Content-Security-Policy' => $this->contentSecurityPolicy(),
        ];

        // HSTS is only meaningful over TLS, and asserting it in local dev would
        // pin developers' browsers to https on localhost.
        if ($request->secure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        foreach ($headers as $header => $value) {
            if (! $response->headers->has($header)) {
                $response->headers->set($header, $value);
            }
        }

        return $response;
    }

    private function contentSecurityPolicy(): string
    {
        $scriptSrc = app()->isProduction() ? "'self'" : "'self' 'unsafe-inline' 'unsafe-eval'";

        return implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "object-src 'none'",
            "img-src 'self' data: https:",
            "font-src 'self' data:",
            "style-src 'self' 'unsafe-inline'",
            "script-src {$scriptSrc}",
            "connect-src 'self'".(app()->isProduction() ? '' : ' ws: http: https:'),
        ]);
    }
}
