<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use IvanBaric\Taxonomy\Exceptions\InvalidTaxonomyAssignmentException;
use IvanBaric\Taxonomy\Models\Taxonomy;
use IvanBaric\Taxonomy\Models\TaxonomyItem;
use IvanBaric\Taxonomy\Tests\Fixtures\Models\Post;

dataset('invalid assignment inputs', [
    'id' => fn (array $fixture) => $fixture['diesel']->getKey(),
    'numeric string id' => fn (array $fixture) => (string) $fixture['diesel']->getKey(),
    'slug' => fn () => 'diesel',
    'model' => fn (array $fixture) => $fixture['diesel'],
    'array' => fn (array $fixture) => [$fixture['diesel']->getKey(), 'diesel'],
    'collection' => fn (array $fixture) => Collection::make([$fixture['diesel'], 'diesel']),
]);

function invalidAssignmentFixture(): array
{
    $brand = Taxonomy::create([
        'name' => 'Brands',
        'type' => 'brand',
    ]);

    $fuel = Taxonomy::create([
        'name' => 'Fuel',
        'type' => 'fuel',
    ]);

    $audi = TaxonomyItem::create([
        'taxonomy_id' => $brand->getKey(),
        'name' => 'Audi',
    ]);

    $bmw = TaxonomyItem::create([
        'taxonomy_id' => $brand->getKey(),
        'name' => 'BMW',
    ]);

    $diesel = TaxonomyItem::create([
        'taxonomy_id' => $fuel->getKey(),
        'name' => 'Diesel',
    ]);

    $post = Post::create(['title' => 'Example']);
    $post->attachTaxonomy('brand', $audi);

    return compact('post', 'brand', 'fuel', 'audi', 'bmw', 'diesel');
}

it('keeps invalid attach inputs silent by default', function (\Closure $makeInput): void {
    $fixture = invalidAssignmentFixture();

    $fixture['post']->attachTaxonomy('brand', $makeInput($fixture));

    expect($fixture['post']->taxonomy('brand')->pluck('name')->all())->toBe(['Audi']);
})->with('invalid assignment inputs');

it('throws explicit exceptions for invalid attach inputs when configured', function (\Closure $makeInput): void {
    config()->set('taxonomy.invalid_assignment_behavior', 'throw');

    $fixture = invalidAssignmentFixture();

    $fixture['post']->attachTaxonomy('brand', $makeInput($fixture));
})->with('invalid assignment inputs')->throws(InvalidTaxonomyAssignmentException::class);

it('syncs valid items and silently drops invalid ones in silent mode', function (): void {
    $fixture = invalidAssignmentFixture();

    $fixture['post']->syncTaxonomy('brand', [
        $fixture['bmw']->getKey(),
        $fixture['diesel']->getKey(),
        'diesel',
    ]);

    expect($fixture['post']->taxonomy('brand')->pluck('name')->all())->toBe(['BMW']);
});

it('attaches valid items and silently drops invalid ones in silent mode', function (): void {
    $fixture = invalidAssignmentFixture();

    $fixture['post']->detachTaxonomy('brand');
    $fixture['post']->attachTaxonomy('brand', [
        $fixture['bmw'],
        $fixture['diesel']->getKey(),
        'diesel',
    ]);

    expect($fixture['post']->taxonomy('brand')->pluck('name')->all())->toBe(['BMW']);
});

it('does not wipe existing assignments when sync receives only invalid inputs in silent mode', function (): void {
    $fixture = invalidAssignmentFixture();

    $fixture['post']->syncTaxonomy('brand', Collection::make([
        $fixture['diesel'],
        'diesel',
        (string) $fixture['diesel']->getKey(),
    ]));

    expect($fixture['post']->taxonomy('brand')->pluck('name')->all())->toBe(['Audi']);
});

it('throws explicit exceptions for invalid sync inputs when configured', function (): void {
    config()->set('taxonomy.invalid_assignment_behavior', 'throw');

    $fixture = invalidAssignmentFixture();

    $fixture['post']->syncTaxonomy('brand', [
        $fixture['bmw']->getKey(),
        $fixture['diesel']->getKey(),
        'diesel',
    ]);
})->throws(InvalidTaxonomyAssignmentException::class);

it('throws explicit exceptions for mixed attach inputs when configured', function (): void {
    config()->set('taxonomy.invalid_assignment_behavior', 'throw');

    $fixture = invalidAssignmentFixture();

    $fixture['post']->detachTaxonomy('brand');
    $fixture['post']->attachTaxonomy('brand', [
        $fixture['bmw'],
        $fixture['diesel']->getKey(),
        'diesel',
    ]);
})->throws(InvalidTaxonomyAssignmentException::class);
