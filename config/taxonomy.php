<?php

declare(strict_types=1);
use IvanBaric\Taxonomy\Models\Taxonomy;
use IvanBaric\Taxonomy\Models\TaxonomyItem;

return [
    /*
    |--------------------------------------------------------------------------
    | Model overrides
    |--------------------------------------------------------------------------
    |
    | These classes are used everywhere inside the package.
    | If your app needs extra traits or relations, extend the package models
    | in your app and point these config values to your own classes.
    |
    | Example:
    | 'taxonomy' => App\Models\Taxonomy::class,
    | 'taxonomy_item' => App\Models\TaxonomyItem::class,
    |
    */
    'models' => [
        // Model for taxonomy groups such as "Categories", "Tags", "Attributes".
        'taxonomy' => Taxonomy::class,

        // Model for individual values inside a taxonomy such as "Laravel", "PHP", "Red".
        'taxonomy_item' => TaxonomyItem::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Invalid assignment behavior
    |--------------------------------------------------------------------------
    |
    | Controls what happens when HasTaxonomies receives inputs that do not
    | resolve to items inside the requested taxonomy type.
    |
    | silent = ignore invalid references and only use valid matches
    | throw  = raise an explicit package exception
    |
    | The default stays "silent" for backward compatibility.
    |
    */
    'invalid_assignment_behavior' => 'silent',

    /*
    |--------------------------------------------------------------------------
    | Optional active-state support
    |--------------------------------------------------------------------------
    |
    | Keep this disabled for generic projects that do not have active/inactive
    | taxonomy columns. If enabled, package read/filter queries will ignore
    | inactive taxonomy groups and inactive taxonomy items.
    |
    | If your custom taxonomy models define a local active() scope, the package
    | uses that scope first. Otherwise it falls back to these column names.
    |
    */
    'activity' => [
        'enabled' => false,
        'taxonomy_column' => 'is_active',
        'taxonomy_item_column' => 'is_active',
    ],

    /*
    |--------------------------------------------------------------------------
    | Optional tenancy support
    |--------------------------------------------------------------------------
    |
    | Keep this disabled if your app does not need team/tenant data isolation.
    | Model overrides are enough for app-level extensions like:
    | - Spatie Translatable
    | - Media Library
    | - UUID traits
    | - custom scopes / relations
    |
    | Enable this only if taxonomy records must be isolated by a tenant key
    | such as team_id, tenant_id, organisation_id, etc.
    |
    */
    'tenancy' => [
        // Turns tenancy behavior on or off for the package.
        // false = package behaves like a normal single-database taxonomy package.
        // true  = package starts using the configured tenancy column and resolver.
        'enabled' => false,

        // Name of the column used for tenancy isolation.
        // Default is team_id, but you can change it to tenant_id or something else.
        'column' => 'team_id',

        // When true, package models get a global scope and queries are automatically
        // filtered to the current tenant/team.
        // Example: Taxonomy::query() will only return rows for the resolved team.
        //
        // When false, no automatic query filtering is applied.
        // Use this only if your app wants to handle tenant filtering manually.
        'apply_global_scope' => true,

        // Controls write behavior when tenancy is enabled but the resolver
        // cannot determine the current tenant/team.
        //
        // true  = create / attach operations throw an exception
        // false = package will not auto-fill the tenancy column
        //
        // Reads are still fail-closed when tenant cannot be resolved.
        // attachTaxonomy(), syncTaxonomy(), and detachTaxonomy() always require
        // a resolved tenant when tenancy is enabled.
        'fail_when_unresolved' => true,

        // Resolver responsible for returning the current tenant key.
        // Expected result: int|string|null
        //
        // You can pass:
        // - a class name that implements IvanBaric\Taxonomy\Contracts\TenantResolver
        // - a resolver instance
        // - null if you want to disable package-level resolution manually
        //
        // Example:
        // 'resolver' => App\Support\CurrentTeamResolver::class,
        'resolver' => IvanBaric\Taxonomy\Resolvers\CorexisTenantResolver::class,

        // Optional tenant model class.
        // This is NOT required for the package to work.
        // Keep it null unless your app wants a concrete model reference for
        // custom relations or helper logic.
        'tenant_model' => null,

        // When tenancy is enabled, the pivot table must also contain the same
        // tenant column so attach/sync/detach operations stay tenant-safe.
        // This package treats pivot isolation as mandatory for multi-tenant use.
        'require_pivot_tenant_column' => true,
    ],

    'permissions' => [
        [
            'name' => 'taxonomy',
            'slug' => 'taxonomy',
            'label' => 'taxonomy::permissions.group',
            'description' => 'taxonomy::permissions.description',
            'icon' => 'tags',
            'sort_order' => 60,
            'items' => [
                ['name' => 'View', 'slug' => 'view', 'code' => 'taxonomy.view', 'label' => 'taxonomy::permissions.view', 'sort_order' => 10],
                ['name' => 'Create', 'slug' => 'create', 'code' => 'taxonomy.create', 'label' => 'taxonomy::permissions.create', 'sort_order' => 20],
                ['name' => 'Update', 'slug' => 'update', 'code' => 'taxonomy.update', 'label' => 'taxonomy::permissions.update', 'sort_order' => 30],
                ['name' => 'Delete', 'slug' => 'delete', 'code' => 'taxonomy.delete', 'label' => 'taxonomy::permissions.delete', 'sort_order' => 40],
                ['name' => 'Attach items', 'slug' => 'attach', 'code' => 'taxonomy.attach', 'label' => 'taxonomy::permissions.attach', 'sort_order' => 50],
                ['name' => 'Sync items', 'slug' => 'sync', 'code' => 'taxonomy.sync', 'label' => 'taxonomy::permissions.sync', 'sort_order' => 60],
            ],
        ],
    ],
];
