<?php

declare(strict_types=1);

namespace IvanBaric\Taxonomy\Support;

use Illuminate\Database\Eloquent\Model;
use IvanBaric\Taxonomy\Contracts\TenantResolver;

class TaxonomyModels
{
    public static function taxonomy(): string
    {
        /** @var class-string<Model> $model */
        $model = config('taxonomy.models.taxonomy', \IvanBaric\Taxonomy\Models\Taxonomy::class);

        return $model;
    }

    public static function taxonomyItem(): string
    {
        /** @var class-string<Model> $model */
        $model = config('taxonomy.models.taxonomy_item', \IvanBaric\Taxonomy\Models\TaxonomyItem::class);

        return $model;
    }

    public static function tenancyEnabled(): bool
    {
        return (bool) config('taxonomy.tenancy.enabled', false);
    }

    public static function tenantColumn(): string
    {
        return (string) config('taxonomy.tenancy.column', 'team_id');
    }

    public static function applyGlobalScope(): bool
    {
        return (bool) config('taxonomy.tenancy.apply_global_scope', true);
    }

    public static function failWhenUnresolved(): bool
    {
        return (bool) config('taxonomy.tenancy.fail_when_unresolved', true);
    }

    public static function tenantModel(): ?string
    {
        $model = config('taxonomy.tenancy.tenant_model');

        return is_string($model) && $model !== '' ? $model : null;
    }

    public static function resolveTenantKey(): int|string|null
    {
        $resolver = config('taxonomy.tenancy.resolver');

        if ($resolver instanceof TenantResolver) {
            return $resolver->resolve();
        }

        if (is_string($resolver) && class_exists($resolver)) {
            $instance = app($resolver);

            if ($instance instanceof TenantResolver) {
                return $instance->resolve();
            }
        }

        return null;
    }
}
