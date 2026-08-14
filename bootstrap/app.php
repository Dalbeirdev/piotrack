<?php

use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnsureEntitled;
use App\Http\Middleware\EnsureHasOrganization;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetApiOrganization;
use App\Http\Middleware\SetCurrentOrganization;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Behind a load balancer / platform proxy in production, trust the
        // forwarded headers so HTTPS, host and client IP are detected correctly.
        $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_HOST
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO
            | Request::HEADER_X_FORWARDED_AWS_ELB);

        $middleware->prepend(AssignRequestId::class);

        // Baseline security headers on every response, API and web alike (SEC-002).
        $middleware->append(SecurityHeaders::class);

        // Inbound billing webhooks authenticate via provider signature, not CSRF.
        // Public marketing form submits + unsubscribes are unauthenticated,
        // cross-origin capture endpoints protected by honeypot + throttling.
        $middleware->validateCsrfTokens(except: ['webhooks/*', 'f/*', 'e/*', 'b/*']);

        $middleware->web(append: [
            SetCurrentOrganization::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Tenant context MUST be established before route-model binding, so the
        // tenant scope is active when {team}/{invitation} are resolved —
        // otherwise binding could load another tenant's record. The API sets its
        // tenant from a header, so its middleware is ordered the same way.
        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: SetCurrentOrganization::class,
        );
        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: SetApiOrganization::class,
        );

        $middleware->alias([
            'organization' => EnsureHasOrganization::class,
            'entitlement' => EnsureEntitled::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->context(fn () => array_filter([
            'request_id' => request()->attributes->get('request_id'),
        ]));
    })->create();
