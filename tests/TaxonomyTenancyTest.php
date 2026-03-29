<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use IvanBaric\Taxonomy\Exceptions\UnresolvedTenantException;
use IvanBaric\Taxonomy\Models\Taxonomy;
use IvanBaric\Taxonomy\Models\TaxonomyItem;
use IvanBaric\Taxonomy\Tests\Fixtures\Models\Post;
use IvanBaric\Taxonomy\Tests\Fixtures\Support\StaticTenantResolver;

beforeEach(function (): void {
    Schema::table('taxonomies', function (Blueprint $table): void {
        $table->unsignedBigInteger('team_id')->nullable()->index();
    });

    Schema::table('taxonomy_items', function (Blueprint $table): void {
        $table->unsignedBigInteger('team_id')->nullable()->index();
    });

    Schema::table('taxonomyables', function (Blueprint $table): void {
        $table->unsignedBigInteger('team_id')->nullable()->index();
    });

    config()->set('taxonomy.tenancy.enabled', true);
    config()->set('taxonomy.tenancy.column', 'team_id');
    config()->set('taxonomy.tenancy.apply_global_scope', true);
    config()->set('taxonomy.tenancy.fail_when_unresolved', true);
    config()->set('taxonomy.tenancy.resolver', StaticTenantResolver::class);

    StaticTenantResolver::$tenantKey = null;
});

it('fails closed for reads and throws on create when tenant is unresolved', function (): void {
    expect(Taxonomy::query()->count())->toBe(0);

    Taxonomy::withoutGlobalScopes()->create([
        'name' => 'Private',
        'type' => 'blog',
        'team_id' => 55,
    ]);

    expect(Taxonomy::query()->count())->toBe(0);

    Taxonomy::create([
        'name' => 'Blocked',
        'type' => 'blog',
    ]);
})->throws(UnresolvedTenantException::class);

it('scopes reads and writes by configured tenant resolver', function (): void {
    StaticTenantResolver::$tenantKey = 10;

    $taxonomy = Taxonomy::create([
        'name' => 'Categories',
        'type' => 'blog',
    ]);

    $item = TaxonomyItem::create([
        'taxonomy_id' => $taxonomy->getKey(),
        'name' => 'News',
    ]);

    $post = Post::create(['title' => 'Tenant aware']);
    $post->attachTaxonomy('blog', $item);

    expect($taxonomy->team_id)->toBe(10);
    expect($item->team_id)->toBe(10);
    expect($post->taxonomy('blog')->pluck('name')->all())->toBe(['News']);

    StaticTenantResolver::$tenantKey = 11;

    expect(Taxonomy::query()->count())->toBe(0);
    expect($post->taxonomy('blog')->count())->toBe(0);
});
