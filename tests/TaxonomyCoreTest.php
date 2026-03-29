<?php

declare(strict_types=1);

use IvanBaric\Taxonomy\Models\Taxonomy;
use IvanBaric\Taxonomy\Models\TaxonomyItem;
use IvanBaric\Taxonomy\Tests\Fixtures\Models\Post;

it('works without translatable and media dependencies', function (): void {
    $taxonomy = Taxonomy::create([
        'name' => 'Tags',
        'type' => 'blog',
        'is_filterable' => true,
        'is_multiple' => true,
    ]);

    $first = TaxonomyItem::create([
        'taxonomy_id' => $taxonomy->getKey(),
        'name' => 'PHP',
    ]);

    TaxonomyItem::create([
        'taxonomy_id' => $taxonomy->getKey(),
        'name' => 'PHP',
    ]);

    $post = Post::create(['title' => 'Plain core']);
    $post->syncTaxonomy('blog', [$first->getKey(), 'php-1']);

    expect($taxonomy->slug)->toBe('tags');
    expect($taxonomy->is_filterable)->toBeTrue();
    expect($taxonomy->is_multiple)->toBeTrue();
    expect($first->slug)->toBe('php');
    expect($taxonomy->items()->ordered()->pluck('slug')->all())->toBe(['php', 'php-1']);
    expect($post->taxonomy('blog')->ordered()->pluck('slug')->all())->toBe(['php', 'php-1']);
});
