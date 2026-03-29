<?php

declare(strict_types=1);

namespace IvanBaric\Taxonomy\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use IvanBaric\Taxonomy\Support\TaxonomyModels;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! TaxonomyModels::tenancyEnabled()) {
            return;
        }

        $tenantKey = TaxonomyModels::resolveTenantKey();

        if ($tenantKey === null || $tenantKey === '') {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($model->qualifyColumn(TaxonomyModels::tenantColumn()), $tenantKey);
    }
}
