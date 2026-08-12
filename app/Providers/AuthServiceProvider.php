<?php

namespace App\Providers;

use App\Authorization\Permission;
use App\Models\User;
use App\Support\CurrentOrganization;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One tenant context per request.
        $this->app->scoped(CurrentOrganization::class);
    }

    public function boot(): void
    {
        // Platform Super Admins bypass every ability (Master Prompt §5, §29).
        Gate::before(function (User $user) {
            return $user->isPlatformSuperAdmin() ? true : null;
        });

        // Each permission is a Gate ability resolved against the user's role in
        // the current organization (ADR-0002). No current org → denied.
        foreach (Permission::cases() as $permission) {
            Gate::define($permission->value, function (User $user) use ($permission) {
                $organization = app(CurrentOrganization::class)->get();

                if ($organization === null) {
                    return false;
                }

                return in_array($permission->value, $user->permissionsIn($organization), true);
            });
        }
    }
}
