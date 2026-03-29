<?php

declare(strict_types=1);

namespace IvanBaric\Taxonomy\Tests\Fixtures\Models;

use IvanBaric\Taxonomy\Models\Taxonomy;

class DealerTaxonomy extends Taxonomy
{
    protected $table = 'taxonomies';

    protected $fillable = [
        'name',
        'slug',
        'type',
        'context',
        'input_type',
        'description',
        'is_filterable',
        'is_required',
        'is_multiple',
        'show_on_detail',
        'show_on_card',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'id' => 'int',
        'is_filterable' => 'bool',
        'is_required' => 'bool',
        'is_multiple' => 'bool',
        'show_on_detail' => 'bool',
        'show_on_card' => 'bool',
        'sort_order' => 'int',
        'is_active' => 'bool',
    ];

    protected $attributes = [
        'is_filterable' => false,
        'is_required' => false,
        'is_multiple' => false,
        'show_on_detail' => false,
        'show_on_card' => false,
        'sort_order' => 0,
        'is_active' => true,
    ];
}
