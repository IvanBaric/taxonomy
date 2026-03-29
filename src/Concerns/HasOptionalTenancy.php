<?php

declare(strict_types=1);

namespace IvanBaric\Taxonomy\Concerns;

use IvanBaric\Taxonomy\Exceptions\UnresolvedTenantException;
use IvanBaric\Taxonomy\Scopes\TenantScope;
use IvanBaric\Taxonomy\Support\TaxonomyModels;

trait HasOptionalTenancy
{
    public static function bootHasOptionalTenancy(): void
    {
        if (TaxonomyModels::tenancyEnabled() && TaxonomyModels::applyGlobalScope()) {
            static::addGlobalScope(new TenantScope());
        }

        static::creating(function ($model): void {
            if (! TaxonomyModels::tenancyEnabled()) {
                return;
            }

            TaxonomyModels::assertTenantColumnExists($model->getTable());

            $column = TaxonomyModels::tenantColumn();

            if ($model->getAttribute($column) !== null) {
                return;
            }

            $tenantKey = TaxonomyModels::resolveTenantKey();

            if ($tenantKey === null || $tenantKey === '') {
                if (TaxonomyModels::failWhenUnresolved()) {
                    throw UnresolvedTenantException::forCreate();
                }

                return;
            }

            $model->setAttribute($column, $tenantKey);
        });
    }
}
