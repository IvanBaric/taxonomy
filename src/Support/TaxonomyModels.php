<?php

declare(strict_types=1);

namespace IvanBaric\Taxonomy\Support;

use Illuminate\Support\Facades\Schema;
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
        return TaxonomyConfigResolver::taxonomyModel();
    }

    public static function taxonomyItem(): string
    {
        return TaxonomyConfigResolver::taxonomyItemModel();
    }

    public static function taxonomiesTable(): string
    {
        return TaxonomyConfigResolver::taxonomiesTable();
    }

    public static function taxonomyItemsTable(): string
    {
        return TaxonomyConfigResolver::taxonomyItemsTable();
    }

    public static function taxonomyablesTable(): string
    {
        return TaxonomyConfigResolver::taxonomyablesTable();
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

    public static function assertPivotTenantColumnExists(?string $table = null): void
    {
        $table ??= static::taxonomyablesTable();
        $column = (string) config('corexis.tenancy.id_column', 'team_id');
        $cacheKey = "{$table}.{$column}";

        if (! array_key_exists($cacheKey, static::$columnExistsCache)) {
            static::$columnExistsCache[$cacheKey] = Schema::hasColumn($table, $column);
        }

        if (! static::$columnExistsCache[$cacheKey]) {
            throw MisconfiguredTenancyException::missingPivotTenantColumn($table, $column);
        }
    }
}
