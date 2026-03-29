<?php

declare(strict_types=1);

use IvanBaric\Taxonomy\Support\TaxonomyModels;

if (! function_exists('taxonomy_model')) {
    function taxonomy_model(): string
    {
        return TaxonomyModels::taxonomy();
    }
}

if (! function_exists('taxonomy_item_model')) {
    function taxonomy_item_model(): string
    {
        return TaxonomyModels::taxonomyItem();
    }
}
