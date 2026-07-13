# ivanbaric/taxonomy

Generic taxonomy package for Laravel 11/12/13. The package core stays intentionally small: two models, one polymorphic attachment trait, minimal migrations, config-driven model resolution, Corexis infrastructure, and generic taxonomy filtering scopes.

## Scope
This package is a reusable taxonomy core.

It provides:
- generic `Taxonomy` and `TaxonomyItem` models
- the `HasTaxonomies` attachment trait
- config-driven model overrides
- Corexis tenant isolation, UUIDs, unique slugs and optimistic locking
- optional Flux Blade field components for admin forms

It does not provide:
- a full admin UI
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
php artisan vendor:publish --tag=taxonomy-views
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

## Flux Field Component

The package ships an optional Flux Blade component for rendering one taxonomy field in an admin form. It supports single-value `flux:select`, multi-value `flux:pillbox`, searchable options, and a Flux modal for adding a new taxonomy item without leaving the form. Use this component only in host apps that already install Flux.

```blade
@foreach ($taxonomyFields as $field)
    <x-taxonomy::field :field="$field" />
@endforeach
```

By default, the component expects these Livewire properties and method on the current component:

```php
public array $taxonomy = [];
public array $taxonomySearch = [];

public function createTaxonomyOption(string $type): void
{
    // Create or resolve the item, then set:
    // $this->taxonomy[$type] = $item->id for single fields
    // $this->taxonomy[$type][] = $item->id for multi fields
}
```

The default bindings are:
- selected values: `taxonomy.{type}`
- modal input/search text: `taxonomySearch.{type}`
- create method: `createTaxonomyOption('{type}')`
- modal name: `create-taxonomy-{type}`
- search placeholder: `Traži`

You can customize those conventions:

```blade
<x-taxonomy::field
    :field="$field"
    model-prefix="vehicleTaxonomy"
    search-prefix="newTaxonomyItem"
    create-method="createVehicleTaxonomyOption"
    modal-prefix="vehicle-taxonomy-"
    :search-placeholder="__('Traži')"
    :create-label="__('Dodaj novi unos')"
/>
```

Publish `taxonomy-views` if a project needs a different layout, copy, or UI library.

The core tables are intentionally minimal:
- `taxonomies`: tenant key, UUID, name, scoped unique slug, type, description, flags, lock version and timestamps
- `taxonomy_items`: tenant key, UUID, taxonomy reference, name, scoped unique slug, description, metadata, position, lock version and timestamps
- `taxonomyables`: tenant key, taxonomy item reference, polymorphic model reference and timestamps

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

`TaxonomyModels` validates every override before it is used. Configured classes must extend their corresponding package models, which keeps relations and Actions replaceable without weakening their contracts.

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

## Tenancy
Taxonomy does not define a tenant resolver or a second tenancy configuration. Configure tenancy once in Corexis:

```php
return [
    'tenancy' => [
        'enabled' => true,
        'id_column' => 'team_id',
        'resolver' => App\Resolvers\TeamResolver::class,
    ],
];
```

Rules:
- `Taxonomy` and `TaxonomyItem` use Corexis `BelongsToTenant` and fail closed when an enabled resolver cannot resolve a tenant.
- The package migrations add the configured Corexis tenant key to models and the polymorphic pivot.
- Pivot tenant isolation is mandatory whenever Corexis tenancy is enabled.
- Attach, sync, detach, item lookup and slug uniqueness remain tenant-scoped.
- The package has no `Team::class`, authentication or host resolver assumptions.

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
- `HasTaxonomies::taxonomyItemIds(string $type)`
- `HasTaxonomies::taxonomyItems()`
- `HasTaxonomies::withTaxonomy(string $type, mixed $items, string $operator = 'any', bool $onlyFilterable = false)`
- `HasTaxonomies::withTaxonomyFilters(array $filters, string $defaultOperator = 'any', bool $onlyFilterable = true)`

## Architecture

`Taxonomy` and `TaxonomyItem` compose Corexis `BelongsToTenant`, `HasUuid`, `HasUniqueSlug` and `HasLockVersion`. Slugs are stable and tenant-scoped; taxonomy item slugs are additionally scoped by their parent taxonomy. Package models do not repeat UUID creation, tenant scopes or collision retry loops.

`TaxonomyAssignment` is the central write service behind `HasTaxonomies` and the attach, detach and sync Actions. It resolves only items visible to the current tenant and keeps pivot tenant data consistent.

The supported write Actions are `CreateTaxonomyAction`, `UpdateTaxonomyAction`, `DeleteTaxonomyAction`, `AttachTaxonomyItemAction`, `DetachTaxonomyItemAction` and `SyncTaxonomyItemsAction`.

The existing `HasTaxonomies` trait API remains stable. New package and UI code should use Actions for state-changing writes:

```php
use IvanBaric\Taxonomy\Actions\SyncTaxonomyItemsAction;

$result = app(SyncTaxonomyItemsAction::class)->handle(
    model: $post,
    type: 'blog',
    items: ['laravel', 'php'],
);
```

Taxonomy Actions return `IvanBaric\Corexis\Data\ActionResult` and dispatch domain events that implement `IvanBaric\Corexis\Contracts\Events\DomainEvent`:

- `TaxonomyCreated`
- `TaxonomyUpdated`
- `TaxonomyDeleted`
- `TaxonomyItemAttached`
- `TaxonomyItemDetached`
- `TaxonomyItemsSynced`

Actions stay model-agnostic. They do not know about pages, posts, products, SEO, gallery, audit, billing, or any host application model beyond the generic Eloquent model passed to attach/detach/sync operations.

## Extensibility boundary
Package core owns:
- generic models
- generic relations
- generic scopes
- attachment trait

Host application owns:
- the single Corexis tenant resolver implementation
- extra columns and migrations
- single-select or required-context validation
- active/inactive rules
- app-specific query helpers
- SEO/status traits

## License
MIT
