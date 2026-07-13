<?php

declare(strict_types=1);

namespace IvanBaric\Taxonomy\Support;

use IvanBaric\Corexis\Support\ConfigResolver;
use IvanBaric\Taxonomy\Models\Taxonomy;
use IvanBaric\Taxonomy\Models\TaxonomyItem;

final class TaxonomyConfigResolver
{
    /** @return class-string<Taxonomy> */
    public static function taxonomyModel(): string
    {
        return app(ConfigResolver::class)->model(
            key: 'taxonomy.models.taxonomy',
            default: Taxonomy::class,
            expectedType: Taxonomy::class,
        );
    }

    /** @return class-string<TaxonomyItem> */
    public static function taxonomyItemModel(): string
    {
        return app(ConfigResolver::class)->model(
            key: 'taxonomy.models.taxonomy_item',
            default: TaxonomyItem::class,
            expectedType: TaxonomyItem::class,
        );
    }

    public static function taxonomiesTable(): string
    {
        return app(ConfigResolver::class)->table(
            key: 'taxonomy.tables.taxonomies',
            default: 'taxonomies',
        );
    }

    public static function taxonomyItemsTable(): string
    {
        return app(ConfigResolver::class)->table(
            key: 'taxonomy.tables.taxonomy_items',
            default: 'taxonomy_items',
        );
    }

    public static function taxonomyablesTable(): string
    {
        return app(ConfigResolver::class)->table(
            key: 'taxonomy.tables.taxonomyables',
            default: 'taxonomyables',
        );
    }
}
