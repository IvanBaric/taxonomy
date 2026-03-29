<?php

declare(strict_types=1);

namespace IvanBaric\Taxonomy\Tests\Fixtures\Models;

use IvanBaric\Taxonomy\Models\TaxonomyItem;

class DealerTaxonomyItem extends TaxonomyItem
{
    protected $table = 'taxonomy_items';

    protected $fillable = [
        'taxonomy_id',
        'name',
        'slug',
        'description',
        'sort_order',
        'position',
        'is_active',
    ];

    protected $casts = [
        'id' => 'int',
        'sort_order' => 'int',
        'position' => 'int',
        'is_active' => 'bool',
    ];

    protected $attributes = [
        'sort_order' => 0,
        'position' => 0,
        'is_active' => true,
    ];
}
