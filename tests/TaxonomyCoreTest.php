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

it('uses corexis uuid and scoped unique slugs', function (): void {
    $categories = Taxonomy::create(['name' => 'Categories', 'type' => 'blog']);
    $duplicate = Taxonomy::create(['name' => 'Categories', 'type' => 'blog']);
    $productCategories = Taxonomy::create(['name' => 'Categories', 'type' => 'products']);

    $first = $categories->items()->create(['name' => 'News']);
    $second = $categories->items()->create(['name' => 'News']);

    expect($categories->uuid)->toBeString()->not->toBeEmpty()
        ->and($categories->getRouteKeyName())->toBe('uuid')
        ->and($categories->slug)->toBe('categories')
        ->and($duplicate->slug)->toBe('categories-1')
        ->and($productCategories->slug)->toBe('categories')
        ->and($first->uuid)->toBeString()->not->toBeEmpty()
        ->and($first->slug)->toBe('news')
        ->and($second->slug)->toBe('news-1');
});

it('stores and retrieves meta array data', function (): void {
    $taxonomy = Taxonomy::create([
        'name' => 'Ingredients',
        'type' => 'ingredients',
    ]);

    $item = $taxonomy->items()->create([
        'name' => 'Wheat flour',
        'meta' => [
            'is_allergen' => true,
            'allergen_type' => 'gluten',
        ],
    ]);

    $fresh = $item->fresh();

    expect($fresh->meta)
        ->toBeArray()
        ->and($fresh->meta['is_allergen'])->toBeTrue()
        ->and($fresh->meta['allergen_type'])->toBe('gluten');
});

it('allows meta to be null', function (): void {
    $taxonomy = Taxonomy::create([
        'name' => 'Tags',
        'type' => 'blog',
    ]);

    $item = $taxonomy->items()->create([
        'name' => 'Laravel',
    ]);

    expect($item->fresh()->meta)->toBeNull();
});

it('updates meta array data', function (): void {
    $taxonomy = Taxonomy::create([
        'name' => 'Tags',
        'type' => 'blog',
    ]);

    $item = $taxonomy->items()->create([
        'name' => 'Laravel',
        'meta' => ['key' => 'value'],
    ]);

    $item->update([
        'meta' => [
            'key' => 'new_value',
            'another' => 'data',
        ],
    ]);

    $fresh = $item->fresh();

    expect($fresh->meta['key'])->toBe('new_value')
        ->and($fresh->meta['another'])->toBe('data');
});

it('stores nested meta array data', function (): void {
    $taxonomy = Taxonomy::create([
        'name' => 'Ingredients',
        'type' => 'ingredients',
    ]);

    $item = $taxonomy->items()->create([
        'name' => 'Wheat flour',
        'meta' => [
            'is_allergen' => true,
            'severity' => 'high',
            'display' => [
                'icon' => 'wheat',
                'color' => '#d6a94c',
            ],
        ],
    ]);

    $fresh = $item->fresh();

    expect($fresh->meta)
        ->toBeArray()
        ->and($fresh->meta['is_allergen'])->toBeTrue()
        ->and($fresh->meta['severity'])->toBe('high')
        ->and($fresh->meta['display']['icon'])->toBe('wheat')
        ->and($fresh->meta['display']['color'])->toBe('#d6a94c');
});
