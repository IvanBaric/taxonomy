<?php

declare(strict_types=1);

use IvanBaric\Taxonomy\Support\TaxonomyModels;
use IvanBaric\Taxonomy\Tests\Fixtures\Models\CustomTaxonomy;
use IvanBaric\Taxonomy\Tests\Fixtures\Models\CustomTaxonomyItem;
use IvanBaric\Taxonomy\Tests\Fixtures\Models\Post;

it('uses configured model overrides across relations and trait queries', function (): void {
    config()->set('taxonomy.models.taxonomy', CustomTaxonomy::class);
    config()->set('taxonomy.models.taxonomy_item', CustomTaxonomyItem::class);

    expect(TaxonomyModels::taxonomy())->toBe(CustomTaxonomy::class);
    expect(TaxonomyModels::taxonomyItem())->toBe(CustomTaxonomyItem::class);

    $taxonomy = CustomTaxonomy::create([
        'name' => 'Categories',
        'type' => 'blog',
        'description' => 'Blog categories',
    ]);

    $item = CustomTaxonomyItem::create([
        'taxonomy_id' => $taxonomy->getKey(),
        'name' => 'Laravel',
        'position' => 1,
    ]);

    $post = Post::create(['title' => 'Example']);
    $post->attachTaxonomy('blog', $item);

    expect($taxonomy->items()->getModel())->toBeInstanceOf(CustomTaxonomyItem::class);
    expect($item->taxonomy()->getModel())->toBeInstanceOf(CustomTaxonomy::class);
    expect($post->taxonomy('blog')->getModel())->toBeInstanceOf(CustomTaxonomyItem::class);
    expect($post->taxonomy('blog')->pluck('name')->all())->toBe(['Laravel']);
    expect($post->hasTaxonomy('blog', 'laravel'))->toBeTrue();
});
