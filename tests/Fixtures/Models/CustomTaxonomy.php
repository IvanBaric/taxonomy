<?php

declare(strict_types=1);

namespace IvanBaric\Taxonomy\Tests\Fixtures\Models;

use IvanBaric\Taxonomy\Models\Taxonomy;

class CustomTaxonomy extends Taxonomy
{
    protected $table = 'taxonomies';
}
