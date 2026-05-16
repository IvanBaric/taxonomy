# ivanbaric/taxonomy

Generic taxonomy package for Laravel 11/12/13. The package core stays intentionally small: two models, one polymorphic attachment trait, minimal migrations, config-driven model resolution, optional tenancy hooks, and generic taxonomy filtering scopes.

## Scope
This package is a reusable taxonomy core.

It provides:
- generic `Taxonomy` and `TaxonomyItem` models
- the `HasTaxonomies` attachment trait
- config-driven model overrides
- optional tenancy hooks

It does not provide:
- admin UI
- media integration
- translatable integration
- SEO/status systems
- business-rule enforcement such as single-select validation

## Installation
```bash
composer require ivanbaric/taxonomy
php artisan migrate
```

Optional publish steps:
```bash
php artisan vendor:publish --tag=taxonomy-config
php artisan vendor:publish --tag=taxonomy-migrations
```

Composer constraints and the package test suite cover:
- Laravel 11
- Laravel 12
- Laravel 13

## Default usage
```php
use App\Models\Post;
use IvanBaric\Taxonomy\Models\Taxonomy;
use IvanBaric\Taxonomy\Traits\HasTaxonomies;

// Enable taxonomy attachment on your model.
class Post extends \Illuminate\Database\Eloquent\Model
{
    use HasTaxonomies;
}

$taxonomy = Taxonomy::create([
    'name' => 'Categories',
    'type' => 'blog',
    'is_filterable' => true,
    'is_multiple' => false,
]);

$item = $taxonomy->items()->create([
    'name' => 'Laravel',
]);

$post->attachTaxonomy('blog', $item);
$post->hasTaxonomy('blog', 'laravel');
```

## Dynamic filtering
Models using `HasTaxonomies` get a public `taxonomyItems()` relation and generic query scopes for runtime-defined filters. This is useful for catalogs where admins can add new filterable taxonomies without changing application code.

```php
use App\Models\Car;

$cars = Car::query()
    ->withTaxonomyFilters([
        'brand' => 'audi',
        'fuel' => ['diesel', 'electric'],
        'feature' => [
            'operator' => 'all',
            'items' => ['navigation', 'camera'],
        ],
    ])
    ->paginate();
```

The example above means:
- `brand` must be `audi`
- `fuel` can be `diesel` or `electric`
- `feature` must contain both `navigation` and `camera`

For request-driven catalogs, keep the request shape generic:

```text
/cars?taxonomies[brand]=audi
/cars?taxonomies[fuel][]=diesel&taxonomies[fuel][]=electric
/cars?taxonomies[feature][operator]=all&taxonomies[feature][items][]=navigation&taxonomies[feature][items][]=camera
```

Then pass the request map directly:

```php
$cars = Car::query()
    ->visible()
    ->withTaxonomyFilters($request->input('taxonomies', []))
    ->latest()
    ->paginate(24)
    ->withQueryString();
```

`withTaxonomyFilters()` only applies taxonomies where `is_filterable = true` by default. Unknown or non-filterable taxonomy types are ignored, which keeps public filtering whitelist-driven by database metadata.

For internal/admin queries, you can opt out of that guard:

```php
$cars = Car::query()->withTaxonomyFilters(
    filters: ['internal_badge' => 'featured'],
    onlyFilterable: false,
)->get();
```

You can also filter one taxonomy directly:

```php
Car::query()->withTaxonomy('brand', 'audi')->get();
Car::query()->withTaxonomy('feature', ['navigation', 'camera'], operator: 'all')->get();
```

The core tables are intentionally minimal:
- `taxonomies`: `id`, `name`, `slug`, `type`, `description`, `is_filterable`, `is_multiple`, timestamps
- `taxonomy_items`: `id`, `taxonomy_id`, `name`, `slug`, `description`, `meta`, `position`, timestamps
- `taxonomyables`: `id`, `taxonomy_item_id`, `taxonomyable_id`, `taxonomyable_type`, timestamps

## Meta column
Each taxonomy item supports an optional `meta` JSON column for host-app specific attributes that don't warrant separate columns or tables.

```php
$taxonomy = Taxonomy::create(['name' => 'Ingredients', 'type' => 'ingredients']);

$taxonomy->items()->create([
    'name' => 'Wheat flour',
    'meta' => [
        'is_allergen' => true,
        'allergen_type' => 'gluten',
    ],
]);

$item = TaxonomyItem::forType('ingredients')->first();
$item->meta['is_allergen']; // true
```

The `meta` column is cast to an array automatically. Use it for:

- Boolean flags (`is_allergen`, `is_featured`)
- Display attributes (`icon`, `color`)
- Lightweight metadata that doesn't need indexing or relations

For data that needs querying performance, indexing, or referential integrity, prefer dedicated columns or tables in the host app.

## Accepted item inputs
`HasTaxonomies` accepts:
- taxonomy item model instance
- integer id
- numeric string id
- slug string
- array
- collection

Behavior:
- duplicate inputs are deduplicated
- blank strings and null-like values are ignored
- repeated attach calls stay idempotent
- `syncTaxonomy([])` clears that taxonomy type
- `syncTaxonomy(null)` is treated as a safe no-op

## Model overrides
The package resolves model classes from config, not from hardcoded package references.

```php
return [
    'models' => [
        'taxonomy' => App\Models\Taxonomy::class,
        'taxonomy_item' => App\Models\TaxonomyItem::class,
    ],
];
```

The helper class used internally is:
- `IvanBaric\Taxonomy\Support\TaxonomyModels::taxonomy()`
- `IvanBaric\Taxonomy\Support\TaxonomyModels::taxonomyItem()`

This allows Spatie-style extension:

```php
namespace App\Models;

use IvanBaric\Taxonomy\Models\Taxonomy as BaseTaxonomy;

class Taxonomy extends BaseTaxonomy
{
}
```

Host apps can use this to add their own traits, casts, relations, and business rules without modifying the package core.

## Invalid assignment behavior
When `attachTaxonomy()` or `syncTaxonomy()` receives values that do not resolve to items inside the requested taxonomy type, the behavior is config-driven.

```php
'invalid_assignment_behavior' => 'silent', // or 'throw'
```

Behavior:
- `silent`: invalid references are ignored and never attached
- `throw`: the package raises `InvalidTaxonomyAssignmentException`
- mixed valid + invalid inputs keep valid matches
- invalid-only sync input does not silently wipe existing assignments

## Optional tenancy
Tenancy is config-driven and disabled by default.

```php
return [
    'tenancy' => [
        'enabled' => true,
        'column' => 'team_id',
        'apply_global_scope' => true,
        'fail_when_unresolved' => true,
        'resolver' => App\Support\TenantResolver::class,
        'tenant_model' => null,
        'require_pivot_tenant_column' => true,
    ],
];
```

Rules:
- Reads are fail-closed when tenancy is enabled and the resolver returns `null`.
- Creates throw an exception when tenancy is enabled, unresolved, and `fail_when_unresolved` is `true`.
- `attachTaxonomy()`, `syncTaxonomy()`, and `detachTaxonomy()` require a resolved tenant when tenancy is enabled.
- Write-side item resolution remains tenant-aware even if `apply_global_scope` is disabled.
- No `Team::class` or `auth()` assumptions exist in package core.
- `tenant_model` is optional and only there for host-app helper use cases.
- Pivot isolation is treated as mandatory for multi-tenant safety.

If you enable tenancy, the host app must add the configured tenant column to:
- `taxonomies`
- `taxonomy_items`
- `taxonomyables`

The host app must also publish and adjust indexes/unique constraints for that schema shape. In particular, tenant-aware slug uniqueness belongs in the host app migration layer when tenancy is enabled.

## API
- `Taxonomy::forType(string $type)`
- `Taxonomy::forSlug(string $slug)`
- `Taxonomy::ordered()`
- `TaxonomyItem::forTaxonomy(Model|int $taxonomy)`
- `TaxonomyItem::forType(string $type)`
- `TaxonomyItem::forSlug(string $slug)`
- `TaxonomyItem::ordered()`
- `HasTaxonomies::taxonomy(string $type)`
- `HasTaxonomies::attachTaxonomy(string $type, mixed $items)`
- `HasTaxonomies::detachTaxonomy(string $type, mixed $items = null)`
- `HasTaxonomies::syncTaxonomy(string $type, mixed $items)`
- `HasTaxonomies::hasTaxonomy(string $type, mixed $item)`
- `HasTaxonomies::taxonomyItems()`
- `HasTaxonomies::withTaxonomy(string $type, mixed $items, string $operator = 'any', bool $onlyFilterable = false)`
- `HasTaxonomies::withTaxonomyFilters(array $filters, string $defaultOperator = 'any', bool $onlyFilterable = true)`

## Extensibility boundary
Package core owns:
- generic models
- generic relations
- generic scopes
- attachment trait

Host application owns:
- tenancy resolver implementation
- extra columns and migrations
- single-select or required-context validation
- active/inactive rules
- app-specific query helpers
- UUID traits
- SEO/status traits

## License
MIT
