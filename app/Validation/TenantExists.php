<?php

declare(strict_types=1);

namespace App\Validation;

use App\Support\CurrentOrganization;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Tenant-scoped `exists` rules.
 *
 * Laravel's exists rule runs a raw query builder lookup, so the Eloquent global
 * `tenant` scope never applies to it: `Rule::exists('forms', 'id')` accepts an
 * id belonging to any organization on the platform. Where the controller then
 * re-queries through Eloquent the scope catches it, but where the validated id
 * is written straight onto a model, a cross-tenant foreign key is persisted.
 *
 * Use these instead of a bare exists rule for any tenant-owned table.
 */
final class TenantExists
{
    /**
     * A record in a tenant-owned table belonging to the current organization.
     */
    public static function in(string $table, string $column = 'id'): Exists
    {
        return Rule::exists($table, $column)
            ->where('organization_id', app(CurrentOrganization::class)->id());
    }

    /**
     * Same, for a table that soft-deletes: excludes trashed rows.
     */
    public static function active(string $table, string $column = 'id'): Exists
    {
        return self::in($table, $column)->whereNull('deleted_at');
    }

    /**
     * A user id, constrained to active members of the current organization.
     *
     * Users are not tenant-owned - one user may belong to several organizations
     * - so membership is what has to be checked, not ownership.
     */
    public static function member(): Exists
    {
        return Rule::exists('organization_user', 'user_id')
            ->where('organization_id', app(CurrentOrganization::class)->id())
            ->where('status', 'active');
    }
}
