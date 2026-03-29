# ivanbaric/taxonomy

Generic taxonomy package for Laravel 11/12. The package core stays intentionally small: two models, one polymorphic attachment trait, minimal migrations, config-driven model resolution, and optional tenancy hooks.

## Default behavior
- No translatable dependency.
- No media library dependency.
- No tenant model requirement.
- No hardcoded auth or team logic.
- No UUID, SEO, status, or custom base model assumptions.

Out of the box the package provides:
- `IvanBaric\Taxonomy\Models\Taxonomy`
- `IvanBaric\Taxonomy\Models\TaxonomyItem`
- `IvanBaric\Taxonomy\Traits\HasTaxonomies`

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

## Default usage
```php
use App\Models\Post;
use IvanBaric\Taxonomy\Models\Taxonomy;
use IvanBaric\Taxonomy\Models\TaxonomyItem;

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

The core tables are intentionally minimal:
- `taxonomies`: `id`, `name`, `slug`, `type`, `description`, `is_filterable`, `is_multiple`, timestamps
- `taxonomy_items`: `id`, `taxonomy_id`, `name`, `slug`, `description`, `position`, timestamps
- `taxonomyables`: `id`, `taxonomy_item_id`, `taxonomyable_id`, `taxonomyable_type`, timestamps

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
    ],
];
```

Rules:
- Reads are fail-closed when tenancy is enabled and the resolver returns `null`.
- Creates throw an exception when tenancy is enabled, unresolved, and `fail_when_unresolved` is `true`.
- No `Team::class` or `auth()` assumptions exist in package core.
- `tenant_model` is optional and only there for host-app helper use cases.

If you enable tenancy, your app migrations must add the configured tenant column where needed, including the pivot table if you want pivot isolation.

## Optional translatable integration
Translatable support is not part of the package core. Override the models in your app and add your own trait:

```php
namespace App\Models;

use IvanBaric\Taxonomy\Models\Taxonomy as BaseTaxonomy;
use Spatie\Translatable\HasTranslations;

class Taxonomy extends BaseTaxonomy
{
    use HasTranslations;

    public array $translatable = ['name', 'description'];
}
```

This package does not require JSON translation columns by default.

## Optional media library integration
Media support is also app-level only:

```php
namespace App\Models;

use IvanBaric\Taxonomy\Models\TaxonomyItem as BaseTaxonomyItem;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class TaxonomyItem extends BaseTaxonomyItem implements HasMedia
{
    use InteractsWithMedia;
}
```

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

Accepted item inputs:
- taxonomy item model instance
- integer id
- slug string
- array or collection of those values

## Extensibility boundary
Package core owns:
- generic models
- generic relations
- generic scopes
- attachment trait

Host application owns:
- tenancy resolver implementation
- extra columns and migrations
- translatable traits
- media traits
- UUID traits
- SEO/status traits

## License
MIT
