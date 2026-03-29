<?php

declare(strict_types=1);

namespace IvanBaric\Taxonomy\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use IvanBaric\Taxonomy\Contracts\TenantResolver;
use IvanBaric\Taxonomy\Exceptions\MisconfiguredTenancyException;

class TaxonomyModels
{
    /**
     * @var array<string, bool>
     */
    protected static array $columnExistsCache = [];

    public static function clearColumnExistsCache(): void
    {
        static::$columnExistsCache = [];
    }

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

    public static function invalidAssignmentBehavior(): string
    {
        $behavior = strtolower((string) config('taxonomy.invalid_assignment_behavior', 'silent'));

        return in_array($behavior, ['silent', 'throw'], true) ? $behavior : 'silent';
    }

    public static function throwsOnInvalidAssignment(): bool
    {
        return static::invalidAssignmentBehavior() === 'throw';
    }

    public static function requirePivotTenantColumn(): bool
    {
        return (bool) config('taxonomy.tenancy.require_pivot_tenant_column', true);
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

    public static function assertTenantColumnExists(string $table): void
    {
        $column = static::tenantColumn();
        $cacheKey = "{$table}.{$column}";

        if (! array_key_exists($cacheKey, static::$columnExistsCache)) {
            static::$columnExistsCache[$cacheKey] = Schema::hasColumn($table, $column);
        }

        if (! static::$columnExistsCache[$cacheKey]) {
            throw MisconfiguredTenancyException::missingTenantColumn($table, $column);
        }
    }

    public static function assertPivotTenantColumnExists(string $table = 'taxonomyables'): void
    {
        if (! static::requirePivotTenantColumn()) {
            return;
        }

        $column = static::tenantColumn();
        $cacheKey = "{$table}.{$column}";

        if (! array_key_exists($cacheKey, static::$columnExistsCache)) {
            static::$columnExistsCache[$cacheKey] = Schema::hasColumn($table, $column);
        }

        if (! static::$columnExistsCache[$cacheKey]) {
            throw MisconfiguredTenancyException::missingPivotTenantColumn($table, $column);
        }
    }
}
