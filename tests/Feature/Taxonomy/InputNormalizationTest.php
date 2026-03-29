<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use IvanBaric\Taxonomy\Models\Taxonomy;
use IvanBaric\Taxonomy\Models\TaxonomyItem;
use IvanBaric\Taxonomy\Tests\Fixtures\Models\Post;

function normalizationFixture(): array
{
    $taxonomy = Taxonomy::create([
        'name' => 'Features',
        'type' => 'feature',
        'is_multiple' => true,
    ]);

    $navigation = TaxonomyItem::create([
        'taxonomy_id' => $taxonomy->getKey(),
        'name' => 'Navigation',
    ]);

    $camera = TaxonomyItem::create([
        'taxonomy_id' => $taxonomy->getKey(),
        'name' => 'Camera',
    ]);

    $post = Post::create(['title' => 'Normalization']);

    return compact('taxonomy', 'navigation', 'camera', 'post');
}

it('normalizes mixed inputs deterministically and deduplicates them', function (): void {
    $fixture = normalizationFixture();

    $fixture['post']->attachTaxonomy('feature', [
        $fixture['navigation']->getKey(),
        (string) $fixture['navigation']->getKey(),
        'navigation',
        $fixture['navigation'],
        Collection::make([
            $fixture['camera']->getKey(),
            'camera',
            null,
            '',
            '   ',
        ]),
    ]);

    expect($fixture['post']->taxonomy('feature')->ordered()->pluck('name')->all())->toBe(['Camera', 'Navigation']);
    expect(DB::table('taxonomyables')->count())->toBe(2);
});

it('prevents duplicate pivot rows across repeated attach calls', function (): void {
    $fixture = normalizationFixture();

    $fixture['post']->attachTaxonomy('feature', $fixture['navigation']);
    $fixture['post']->attachTaxonomy('feature', $fixture['navigation']->getKey());
    $fixture['post']->attachTaxonomy('feature', 'navigation');

    expect(DB::table('taxonomyables')->count())->toBe(1);
});

it('keeps sync stable after repeated attach attempts', function (): void {
    $fixture = normalizationFixture();

    $fixture['post']->attachTaxonomy('feature', [
        $fixture['navigation'],
        $fixture['navigation']->getKey(),
        'navigation',
    ]);

    $fixture['post']->syncTaxonomy('feature', [$fixture['camera']->getKey()]);

    expect(DB::table('taxonomyables')->count())->toBe(1);
    expect($fixture['post']->taxonomy('feature')->pluck('name')->all())->toBe(['Camera']);
});

it('treats explicit empty sets as clear-all but keeps null-like sync inputs as no-op', function (): void {
    $fixture = normalizationFixture();

    $fixture['post']->attachTaxonomy('feature', [
        $fixture['navigation'],
        $fixture['camera'],
    ]);

    $fixture['post']->syncTaxonomy('feature', null);
    expect($fixture['post']->taxonomy('feature')->ordered()->pluck('name')->all())->toBe(['Camera', 'Navigation']);

    $fixture['post']->syncTaxonomy('feature', []);
    expect($fixture['post']->taxonomy('feature')->count())->toBe(0);
});

it('detaches cleanly after duplicate attach attempts', function (): void {
    $fixture = normalizationFixture();

    $fixture['post']->attachTaxonomy('feature', [
        $fixture['navigation'],
        $fixture['navigation']->getKey(),
        'navigation',
    ]);

    $fixture['post']->detachTaxonomy('feature', [
        $fixture['navigation']->getKey(),
        'navigation',
        Collection::make([$fixture['navigation']]),
    ]);

    expect(DB::table('taxonomyables')->count())->toBe(0);
    expect($fixture['post']->taxonomy('feature')->count())->toBe(0);
});
