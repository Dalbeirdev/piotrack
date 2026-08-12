<?php

namespace App\Models\Concerns;

use App\Models\Organization;
use App\Support\CurrentOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Row-level tenant isolation (TEN-004). A model using this trait is:
 *  - automatically scoped to the current organization on every query,
 *  - stamped with the current organization_id on create, and
 *  - protected against having its organization_id changed after creation.
 *
 * The scope is active whenever a current organization is set. Tenant web
 * routes always have one (SetCurrentOrganization middleware); platform,
 * console and aggregation code that must cross tenants opts out explicitly
 * via withoutTenantScope().
 *
 * @phpstan-require-extends Model
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            $organizationId = app(CurrentOrganization::class)->id();

            if ($organizationId !== null) {
                $builder->where($builder->getModel()->getTable().'.organization_id', $organizationId);
            }
        });

        static::creating(function (Model $model) {
            if ($model->getAttribute('organization_id') === null) {
                $model->setAttribute('organization_id', app(CurrentOrganization::class)->id());
            }
        });

        static::updating(function (Model $model) {
            if ($model->isDirty('organization_id') && $model->getOriginal('organization_id') !== null) {
                throw new RuntimeException('The organization_id of a tenant-owned record is immutable.');
            }
        });
    }

    /**
     * Escape hatch for platform/admin/aggregation code that must query across
     * tenants. Use deliberately and sparingly.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithoutTenantScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope('tenant');
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
