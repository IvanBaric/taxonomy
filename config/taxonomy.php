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

    'tables' => [
        'taxonomies' => 'taxonomies',
        'taxonomy_items' => 'taxonomy_items',
        'taxonomyables' => 'taxonomyables',
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
