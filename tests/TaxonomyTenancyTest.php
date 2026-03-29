<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use IvanBaric\Taxonomy\Exceptions\MisconfiguredTenancyException;
use IvanBaric\Taxonomy\Exceptions\UnresolvedTenantException;
use IvanBaric\Taxonomy\Models\Taxonomy;
use IvanBaric\Taxonomy\Models\TaxonomyItem;
use IvanBaric\Taxonomy\Tests\Fixtures\Models\Post;
use IvanBaric\Taxonomy\Tests\Fixtures\Support\StaticTenantResolver;

beforeEach(function (): void {
    $this->enableTeamTenancy();

    config()->set('taxonomy.tenancy.resolver', StaticTenantResolver::class);

    StaticTenantResolver::$tenantKey = null;
});

function createTenantTaxonomy(string $type, string $taxonomyName, array $itemNames): array
{
    $taxonomy = Taxonomy::create([
        'name' => $taxonomyName,
        'type' => $type,
    ]);

    $items = collect($itemNames)
        ->mapWithKeys(fn (string $name) => [
            $name => TaxonomyItem::create([
                'taxonomy_id' => $taxonomy->getKey(),
                'name' => $name,
            ]),
        ]);

    return [$taxonomy, $items];
}

it('fails closed on unresolved reads and throws on unresolved writes', function (): void {
    DB::table('taxonomies')->insert([
        'name' => 'Categories',
        'slug' => 'categories',
        'type' => 'blog',
        'team_id' => 55,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(Taxonomy::query()->count())->toBe(0);

    $post = Post::create(['title' => 'Tenant aware']);

    expect(fn () => Taxonomy::create([
        'name' => 'Blocked',
        'type' => 'blog',
    ]))->toThrow(UnresolvedTenantException::class);

    expect(fn () => $post->attachTaxonomy('blog', [1]))->toThrow(UnresolvedTenantException::class);
    expect(fn () => $post->syncTaxonomy('blog', []))->toThrow(UnresolvedTenantException::class);
    expect(fn () => $post->detachTaxonomy('blog'))->toThrow(UnresolvedTenantException::class);
});

it('keeps pivot rows isolated between tenants during detach on the same model', function (): void {
    $post = Post::create(['title' => 'Shared model']);

    StaticTenantResolver::$tenantKey = 10;
    [, $tenantOneItems] = createTenantTaxonomy('blog', 'Categories', ['News']);
    $post->attachTaxonomy('blog', $tenantOneItems['News']);

    StaticTenantResolver::$tenantKey = 20;
    [, $tenantTwoItems] = createTenantTaxonomy('blog', 'Categories', ['News']);
    $post->attachTaxonomy('blog', $tenantTwoItems['News']);

    expect(DB::table('taxonomyables')->count())->toBe(2);

    StaticTenantResolver::$tenantKey = 10;
    $post->detachTaxonomy('blog', 'news');

    expect(DB::table('taxonomyables')->count())->toBe(1);
    expect(DB::table('taxonomyables')->value('team_id'))->toBe(20);
    expect($post->taxonomy('blog')->count())->toBe(0);

    StaticTenantResolver::$tenantKey = 20;
    expect($post->taxonomy('blog')->pluck('name')->all())->toBe(['News']);
});

it('keeps pivot rows isolated between tenants during sync on the same model', function (): void {
    $post = Post::create(['title' => 'Shared model']);

    StaticTenantResolver::$tenantKey = 10;
    [, $tenantOneItems] = createTenantTaxonomy('blog', 'Categories', ['News', 'Updates']);
    $post->attachTaxonomy('blog', $tenantOneItems['News']);

    StaticTenantResolver::$tenantKey = 20;
    [, $tenantTwoItems] = createTenantTaxonomy('blog', 'Categories', ['News', 'Updates']);
    $post->attachTaxonomy('blog', $tenantTwoItems['News']);

    StaticTenantResolver::$tenantKey = 10;
    $post->syncTaxonomy('blog', [$tenantOneItems['Updates']->getKey()]);

    expect(DB::table('taxonomyables')->count())->toBe(2);

    $tenantOneRows = DB::table('taxonomyables')->where('team_id', 10)->pluck('taxonomy_item_id')->all();
    $tenantTwoRows = DB::table('taxonomyables')->where('team_id', 20)->pluck('taxonomy_item_id')->all();

    expect($tenantOneRows)->toBe([$tenantOneItems['Updates']->getKey()]);
    expect($tenantTwoRows)->toBe([$tenantTwoItems['News']->getKey()]);
});

it('keeps write-side item resolution tenant-safe even when global scope is disabled', function (): void {
    $this->enableTeamTenancy(configOverrides: ['apply_global_scope' => false]);
    config()->set('taxonomy.tenancy.resolver', StaticTenantResolver::class);

    $post = Post::create(['title' => 'Cross-tenant safety']);

    StaticTenantResolver::$tenantKey = 10;
    [, $tenantOneItems] = createTenantTaxonomy('brand', 'Brands', ['Audi']);

    StaticTenantResolver::$tenantKey = 20;
    [, $tenantTwoItems] = createTenantTaxonomy('brand', 'Brands', ['BMW']);

    StaticTenantResolver::$tenantKey = 10;
    $post->attachTaxonomy('brand', [$tenantTwoItems['BMW']->getKey()]);
    $post->attachTaxonomy('brand', [$tenantOneItems['Audi']->getKey()]);

    expect($post->taxonomy('brand')->pluck('name')->all())->toBe(['Audi']);
});

it('allows identical taxonomy slugs across tenants when tenant-aware schema is configured', function (): void {
    StaticTenantResolver::$tenantKey = 10;
    $first = Taxonomy::create([
        'name' => 'Categories',
        'type' => 'blog',
    ]);

    StaticTenantResolver::$tenantKey = 20;
    $second = Taxonomy::create([
        'name' => 'Categories',
        'type' => 'blog',
    ]);

    expect($first->slug)->toBe('categories');
    expect($second->slug)->toBe('categories');
    expect(DB::table('taxonomies')->count())->toBe(2);
});

it('throws a clear exception when pivot tenant isolation is required but not configured', function (): void {
    $this->enableTeamTenancy(withPivotTenantColumn: false, withTenantAwarePivotUnique: false);
    config()->set('taxonomy.tenancy.resolver', StaticTenantResolver::class);
    config()->set('taxonomy.tenancy.require_pivot_tenant_column', true);

    StaticTenantResolver::$tenantKey = 10;

    $post = Post::create(['title' => 'Pivot config']);
    [, $items] = createTenantTaxonomy('blog', 'Categories', ['News']);

    $post->attachTaxonomy('blog', $items['News']);
})->throws(MisconfiguredTenancyException::class);
