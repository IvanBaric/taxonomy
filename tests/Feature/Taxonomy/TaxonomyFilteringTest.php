<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use IvanBaric\Taxonomy\Support\TaxonomyModels;
use IvanBaric\Taxonomy\Tests\Fixtures\Models\Car;
use IvanBaric\Taxonomy\Tests\Fixtures\Support\CarDealerTaxonomyScenario;

beforeEach(function (): void {
    CarDealerTaxonomyScenario::configureModels();
});

function carsWith(string $context, string $itemName): Builder
{
    $taxonomyItemModel = TaxonomyModels::taxonomyItem();
    $id = $taxonomyItemModel::query()
        ->forType($context)
        ->where('name', $itemName)
        ->value('id');

    // This query shape is what a real app ends up writing today.
    // It's workable, but verbose and easy to get subtly wrong.
    return Car::query()->whereHas('taxonomyItems', function (Builder $query) use ($context, $id): void {
        $query->where('taxonomy_items.id', $id)
            ->whereHas('taxonomy', fn (Builder $taxonomy) => $taxonomy->where('type', $context));
    });
}

it('filters by one taxonomy context (brand Audi)', function (): void {
    CarDealerTaxonomyScenario::seed();

    $cars = carsWith('brand', 'Audi')->pluck('title')->all();

    expect($cars)->toContain('Audi A4 2.0 TDI', 'Audi Q4 e-tron quattro');
});

it('filters by one taxonomy context (feature Navigation)', function (): void {
    CarDealerTaxonomyScenario::seed();

    $cars = carsWith('feature', 'Navigation')->pluck('title')->all();

    expect($cars)->toContain('Audi A4 2.0 TDI', 'Audi Q4 e-tron quattro', 'BMW X5 xDrive45e');
});

it('filters by multiple taxonomy contexts (brand BMW and fuel Diesel)', function (): void {
    CarDealerTaxonomyScenario::seed();

    $cars = carsWith('brand', 'BMW')
        ->whereHas('taxonomyItems', function (Builder $query): void {
            $id = TaxonomyModels::taxonomyItem()::query()->forType('fuel')->where('name', 'Diesel')->value('id');
            $query->where('taxonomy_items.id', $id)
                ->whereHas('taxonomy', fn (Builder $taxonomy) => $taxonomy->where('type', 'fuel'));
        })
        ->pluck('title')
        ->all();

    expect($cars)->toBe(['BMW 320d Manual']);
});

it('filters by AND across multiple items in same context (Camera + Navigation)', function (): void {
    CarDealerTaxonomyScenario::seed();

    $cameraId = TaxonomyModels::taxonomyItem()::query()->forType('feature')->where('name', 'Camera')->value('id');
    $navId = TaxonomyModels::taxonomyItem()::query()->forType('feature')->where('name', 'Navigation')->value('id');

    $cars = Car::query()
        ->whereHas('taxonomyItems', fn (Builder $q) => $q->where('taxonomy_items.id', $cameraId)->whereHas('taxonomy', fn (Builder $t) => $t->where('type', 'feature')))
        ->whereHas('taxonomyItems', fn (Builder $q) => $q->where('taxonomy_items.id', $navId)->whereHas('taxonomy', fn (Builder $t) => $t->where('type', 'feature')))
        ->pluck('title')
        ->all();

    expect($cars)->toContain('Audi A4 2.0 TDI', 'Audi Q4 e-tron quattro');
});

it('filters visible cars with multiple constraints (Audi + Registered badge)', function (): void {
    CarDealerTaxonomyScenario::seed();

    $audiId = TaxonomyModels::taxonomyItem()::query()->forType('brand')->where('name', 'Audi')->value('id');
    $registeredId = TaxonomyModels::taxonomyItem()::query()->forType('seller_badge')->where('name', 'Registered')->value('id');

    $cars = Car::query()
        ->visible()
        ->whereHas('taxonomyItems', fn (Builder $q) => $q->where('taxonomy_items.id', $audiId)->whereHas('taxonomy', fn (Builder $t) => $t->where('type', 'brand')))
        ->whereHas('taxonomyItems', fn (Builder $q) => $q->where('taxonomy_items.id', $registeredId)->whereHas('taxonomy', fn (Builder $t) => $t->where('type', 'seller_badge')))
        ->pluck('title')
        ->all();

    expect($cars)->toBe(['Audi A4 2.0 TDI', 'Audi Q4 e-tron quattro']);
});

