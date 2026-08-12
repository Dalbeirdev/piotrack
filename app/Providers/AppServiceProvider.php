<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // In production always generate HTTPS URLs (assets, redirects, signed
        // links). Combined with trusted proxies this keeps signed URLs valid
        // behind a TLS-terminating load balancer.
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        // AUTH-003: platform password policy. Length over composition (NIST-aligned);
        // breached-password check only in production to keep tests/local offline.
        Password::defaults(function () {
            $rule = Password::min(12);

            return $this->app->isProduction() ? $rule->uncompromised() : $rule;
        });

        // RecordAuthEvents is wired by Laravel's listener auto-discovery
        // (app/Listeners, handle* methods) — do not ALSO subscribe it
        // manually, or every audit event is recorded twice.
    }
}
