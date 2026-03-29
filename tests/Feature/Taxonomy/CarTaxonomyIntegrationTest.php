<?php

declare(strict_types=1);

use IvanBaric\Taxonomy\Support\TaxonomyModels;
use IvanBaric\Taxonomy\Tests\Fixtures\Models\Car;
use IvanBaric\Taxonomy\Tests\Fixtures\Support\CarDealerTaxonomyScenario;

beforeEach(function (): void {
    CarDealerTaxonomyScenario::configureModels();
});

it('creates dealer taxonomy groups and items (realistic car dealer catalog)', function (): void {
    $seed = CarDealerTaxonomyScenario::seed();

    expect($seed['taxonomies']->keys()->all())->toContain('brand');
    expect($seed['items']['brand']->keys()->all())->toBe(['Audi', 'BMW', 'VW']);
    expect($seed['items']['fuel']->keys()->all())->toContain('Diesel', 'Electric');
});

it('retrieves taxonomy items by context/type cleanly enough', function (): void {
    $seed = CarDealerTaxonomyScenario::seed();

    $taxonomyItemModel = TaxonomyModels::taxonomyItem();

    $brands = $taxonomyItemModel::query()
        ->forType('brand')
        ->ordered()
        ->pluck('name')
        ->all();

    $features = $taxonomyItemModel::query()
        ->forType('feature')
        ->ordered()
        ->pluck('name')
        ->all();

    expect($brands)->toBe(['Audi', 'BMW', 'VW']);
    expect($features)->toContain('Navigation', 'Camera', 'Heated Seats');
});

it('assigns taxonomies to cars and can display them grouped by context', function (): void {
    $seed = CarDealerTaxonomyScenario::seed();

    /** @var Car $car */
    $car = $seed['cars']['audi_diesel_auto'];

    // Grouped display example for detail view.
    $grouped = $car->taxonomyItems()
        ->with('taxonomy')
        ->get()
        ->groupBy(fn ($item) => $item->taxonomy->type)
        ->map(fn ($items) => $items->pluck('name')->values()->all())
        ->all();

    expect($grouped['brand'])->toBe(['Audi']);
    expect($grouped['fuel'])->toBe(['Diesel']);
    expect($grouped['transmission'])->toBe(['Automatic']);
    expect($grouped['feature'])->toContain('Navigation', 'Camera');
});

