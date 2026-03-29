<?php

declare(strict_types=1);

namespace IvanBaric\Taxonomy\Tests\Fixtures\Support;

use Illuminate\Support\Collection;
use IvanBaric\Taxonomy\Tests\Fixtures\Models\Car;
use IvanBaric\Taxonomy\Tests\Fixtures\Models\DealerTaxonomy;
use IvanBaric\Taxonomy\Tests\Fixtures\Models\DealerTaxonomyItem;

class CarDealerTaxonomyScenario
{
    public static function configureModels(): void
    {
        config()->set('taxonomy.models.taxonomy', DealerTaxonomy::class);
        config()->set('taxonomy.models.taxonomy_item', DealerTaxonomyItem::class);
    }

    public static function seed(): array
    {
        self::configureModels();

        $taxonomies = collect([
            self::createTaxonomy('brand', 'Brand', 'single_select', true, true, true, 10),
            self::createTaxonomy('fuel', 'Fuel', 'single_select', true, true, true, 20),
            self::createTaxonomy('transmission', 'Transmission', 'single_select', true, true, true, 30),
            self::createTaxonomy('body_type', 'Body Type', 'single_select', true, true, true, 40),
            self::createTaxonomy('drivetrain', 'Drivetrain', 'single_select', true, true, true, 50),
            self::createTaxonomy('condition', 'Condition', 'single_select', true, true, true, 60),
            self::createTaxonomy('color', 'Color', 'single_select', true, false, true, 70),
            self::createTaxonomy('feature', 'Features', 'multi_select', true, false, true, 80),
            self::createTaxonomy('safety_feature', 'Safety Features', 'multi_select', true, false, true, 90),
            self::createTaxonomy('comfort_feature', 'Comfort Features', 'multi_select', false, false, true, 100),
            self::createTaxonomy('multimedia_feature', 'Multimedia Features', 'multi_select', false, false, true, 110),
            self::createTaxonomy('seller_badge', 'Seller Badges', 'multi_select', true, false, false, 120),
        ])->keyBy('type');

        $items = [
            'brand' => self::createItems($taxonomies['brand'], ['Audi', 'BMW', 'VW']),
            'fuel' => self::createItems($taxonomies['fuel'], ['Diesel', 'Petrol', 'Hybrid', 'Electric']),
            'transmission' => self::createItems($taxonomies['transmission'], ['Manual', 'Automatic']),
            'body_type' => self::createItems($taxonomies['body_type'], ['Hatchback', 'Sedan', 'SUV']),
            'drivetrain' => self::createItems($taxonomies['drivetrain'], ['FWD', 'RWD', 'AWD']),
            'condition' => self::createItems($taxonomies['condition'], ['Used', 'New']),
            'color' => self::createItems($taxonomies['color'], ['Black', 'White', 'Gray']),
            'feature' => self::createItems($taxonomies['feature'], ['Navigation', 'Camera', 'Cruise Control', 'Heated Seats']),
            'safety_feature' => self::createItems($taxonomies['safety_feature'], ['ABS', 'ESP', 'Lane Assist']),
            'comfort_feature' => self::createItems($taxonomies['comfort_feature'], ['Dual Zone AC', 'Leather Seats']),
            'multimedia_feature' => self::createItems($taxonomies['multimedia_feature'], ['Apple CarPlay', 'Android Auto']),
            'seller_badge' => self::createItems($taxonomies['seller_badge'], ['First Owner', 'Service History', 'Registered']),
        ];

        $cars = [
            'audi_diesel_auto' => self::createCar('Audi A4 2.0 TDI', 23500, 2021, 68500, 150, true),
            'bmw_diesel_manual' => self::createCar('BMW 320d Manual', 21900, 2020, 84500, 190, true),
            'vw_petrol_auto' => self::createCar('VW Golf 1.5 TSI DSG', 19800, 2019, 72400, 150, true),
            'audi_electric_awd' => self::createCar('Audi Q4 e-tron quattro', 46900, 2023, 12500, 299, true),
            'bmw_hybrid_badges' => self::createCar('BMW X5 xDrive45e', 58900, 2022, 40200, 394, false),
        ];

        self::assignSingle($cars['audi_diesel_auto'], $items, [
            'brand' => 'Audi',
            'fuel' => 'Diesel',
            'transmission' => 'Automatic',
            'body_type' => 'Sedan',
            'drivetrain' => 'FWD',
            'condition' => 'Used',
            'color' => 'Black',
        ]);
        self::assignMany($cars['audi_diesel_auto'], $items, [
            'feature' => ['Navigation', 'Camera'],
            'safety_feature' => ['ABS', 'ESP'],
            'comfort_feature' => ['Dual Zone AC'],
            'seller_badge' => ['Service History', 'Registered'],
        ]);

        self::assignSingle($cars['bmw_diesel_manual'], $items, [
            'brand' => 'BMW',
            'fuel' => 'Diesel',
            'transmission' => 'Manual',
            'body_type' => 'Sedan',
            'drivetrain' => 'RWD',
            'condition' => 'Used',
            'color' => 'Gray',
        ]);
        self::assignMany($cars['bmw_diesel_manual'], $items, [
            'feature' => ['Heated Seats'],
            'safety_feature' => ['ABS'],
            'comfort_feature' => ['Leather Seats'],
            'seller_badge' => ['First Owner'],
        ]);

        self::assignSingle($cars['vw_petrol_auto'], $items, [
            'brand' => 'VW',
            'fuel' => 'Petrol',
            'transmission' => 'Automatic',
            'body_type' => 'Hatchback',
            'drivetrain' => 'FWD',
            'condition' => 'Used',
            'color' => 'White',
        ]);
        self::assignMany($cars['vw_petrol_auto'], $items, [
            'feature' => ['Cruise Control'],
            'multimedia_feature' => ['Apple CarPlay'],
            'seller_badge' => ['Registered'],
        ]);

        self::assignSingle($cars['audi_electric_awd'], $items, [
            'brand' => 'Audi',
            'fuel' => 'Electric',
            'transmission' => 'Automatic',
            'body_type' => 'SUV',
            'drivetrain' => 'AWD',
            'condition' => 'New',
            'color' => 'Gray',
        ]);
        self::assignMany($cars['audi_electric_awd'], $items, [
            'feature' => ['Navigation', 'Camera'],
            'safety_feature' => ['Lane Assist'],
            'multimedia_feature' => ['Android Auto'],
            'seller_badge' => ['Registered'],
        ]);

        self::assignSingle($cars['bmw_hybrid_badges'], $items, [
            'brand' => 'BMW',
            'fuel' => 'Hybrid',
            'transmission' => 'Automatic',
            'body_type' => 'SUV',
            'drivetrain' => 'AWD',
            'condition' => 'Used',
            'color' => 'Black',
        ]);
        self::assignMany($cars['bmw_hybrid_badges'], $items, [
            'feature' => ['Navigation', 'Heated Seats'],
            'comfort_feature' => ['Leather Seats'],
            'multimedia_feature' => ['Apple CarPlay', 'Android Auto'],
            'seller_badge' => ['First Owner', 'Service History', 'Registered'],
        ]);

        return compact('taxonomies', 'items', 'cars');
    }

    protected static function createTaxonomy(
        string $type,
        string $name,
        string $inputType,
        bool $isFilterable,
        bool $showOnCard,
        bool $showOnDetail,
        int $sortOrder
    ): DealerTaxonomy {
        return DealerTaxonomy::create([
            'name' => $name,
            'type' => $type,
            'context' => $type,
            'input_type' => $inputType,
            'is_filterable' => $isFilterable,
            'is_required' => in_array($type, ['brand', 'fuel', 'transmission', 'body_type', 'drivetrain', 'condition'], true),
            'is_multiple' => $inputType === 'multi_select',
            'show_on_card' => $showOnCard,
            'show_on_detail' => $showOnDetail,
            'sort_order' => $sortOrder,
            'is_active' => true,
        ]);
    }

    protected static function createItems(DealerTaxonomy $taxonomy, array $names): Collection
    {
        return collect($names)
            ->values()
            ->map(fn (string $name, int $index) => $taxonomy->items()->create([
                'name' => $name,
                'sort_order' => ($index + 1) * 10,
                'position' => $index + 1,
                'is_active' => true,
            ]))
            ->keyBy('name');
    }

    protected static function createCar(
        string $title,
        int $price,
        int $year,
        int $mileage,
        ?int $power,
        bool $visible
    ): Car {
        return Car::create([
            'title' => $title,
            'price' => $price,
            'year' => $year,
            'mileage' => $mileage,
            'power' => $power,
            'description' => $title.' description',
            'is_visible' => $visible,
        ]);
    }

    protected static function assignSingle(Car $car, array $items, array $assignments): void
    {
        foreach ($assignments as $context => $name) {
            $car->syncTaxonomy($context, [$items[$context][$name]->getKey()]);
        }
    }

    protected static function assignMany(Car $car, array $items, array $assignments): void
    {
        foreach ($assignments as $context => $names) {
            $car->syncTaxonomy(
                $context,
                collect($names)->map(fn (string $name) => $items[$context][$name]->getKey())->all()
            );
        }
    }
}
