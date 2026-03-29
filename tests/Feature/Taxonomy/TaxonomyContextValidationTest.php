<?php

declare(strict_types=1);

use IvanBaric\Taxonomy\Support\TaxonomyModels;
use IvanBaric\Taxonomy\Tests\Fixtures\Models\Car;
use IvanBaric\Taxonomy\Tests\Fixtures\Support\CarDealerTaxonomyScenario;

beforeEach(function (): void {
    CarDealerTaxonomyScenario::configureModels();
});

it('blocks cross-context assignment by id lookup (silent no-op, not a hard failure)', function (): void {
    $seed = CarDealerTaxonomyScenario::seed();

    /** @var Car $car */
    $car = $seed['cars']['vw_petrol_auto'];

    // Try to attach a Fuel item while claiming context is Brand.
    $dieselId = $seed['items']['fuel']['Diesel']->getKey();
    $car->attachTaxonomy('brand', [$dieselId]);

    // Expect: nothing attached under brand. This is "safe" but the DX is weak:
    // the caller gets no feedback that the assignment failed.
    expect($car->taxonomy('brand')->pluck('name')->all())->toBe(['VW']);
});

it('does not enforce single-select contexts (serious weakness for production)', function (): void {
    $seed = CarDealerTaxonomyScenario::seed();

    /** @var Car $car */
    $car = $seed['cars']['audi_diesel_auto'];

    // Brand is single-select in our domain metadata, but the package does not enforce it.
    $audiId = $seed['items']['brand']['Audi']->getKey();
    $bmwId = $seed['items']['brand']['BMW']->getKey();

    // Attaching a second brand is possible (and syncTaxonomy can also accept multiple ids).
    $car->attachTaxonomy('brand', [$bmwId]);
    $car->attachTaxonomy('brand', [$audiId]);

    $brands = $car->taxonomy('brand')->ordered()->pluck('name')->all();

    // If this assertion ever fails, it means single-select started being enforced.
    // Right now, it passes and documents the lack of constraint.
    expect($brands)->toBe(['Audi', 'BMW']);
});

it('allows attaching inactive taxonomy items (serious weakness for production)', function (): void {
    $seed = CarDealerTaxonomyScenario::seed();

    $taxonomyItemModel = TaxonomyModels::taxonomyItem();
    $navigation = $seed['items']['feature']['Navigation'];

    // Simulate a business rule: items can be inactive but should be non-assignable.
    $taxonomyItemModel::query()->whereKey($navigation->getKey())->update(['is_active' => false]);

    /** @var Car $car */
    $car = $seed['cars']['bmw_diesel_manual'];

    $car->attachTaxonomy('feature', [$navigation->getKey()]);

    // Current behavior: item is still attachable and shows up.
    // This is a gap: package has no built-in "active-only" constraints.
    expect($car->taxonomy('feature')->pluck('name')->all())->toContain('Navigation');
});

