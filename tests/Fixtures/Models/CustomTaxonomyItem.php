<?php

declare(strict_types=1);

namespace IvanBaric\Taxonomy\Tests\Fixtures\Models;

use IvanBaric\Taxonomy\Models\TaxonomyItem;

class CustomTaxonomyItem extends TaxonomyItem
{
    protected $table = 'taxonomy_items';
}
