<?php

declare(strict_types=1);

namespace IvanBaric\Taxonomy\Tests;

use Illuminate\Database\Schema\Blueprint;
use IvanBaric\Taxonomy\Models\Taxonomy;
use IvanBaric\Taxonomy\Models\TaxonomyItem;
use Illuminate\Support\Facades\Schema;
use IvanBaric\Taxonomy\Support\TaxonomyModels;
use IvanBaric\Taxonomy\TaxonomyServiceProvider;
use IvanBaric\Taxonomy\Tests\Fixtures\Models\Car;
use IvanBaric\Taxonomy\Tests\Fixtures\Models\CustomTaxonomy;
use IvanBaric\Taxonomy\Tests\Fixtures\Models\CustomTaxonomyItem;
use IvanBaric\Taxonomy\Tests\Fixtures\Models\DealerTaxonomy;
use IvanBaric\Taxonomy\Tests\Fixtures\Models\DealerTaxonomyItem;
use IvanBaric\Taxonomy\Tests\Fixtures\Models\Post;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            TaxonomyServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Taxonomy::clearBootedModels();
        TaxonomyItem::clearBootedModels();
        CustomTaxonomy::clearBootedModels();
        CustomTaxonomyItem::clearBootedModels();
        DealerTaxonomy::clearBootedModels();
        DealerTaxonomyItem::clearBootedModels();
        Car::clearBootedModels();
        Post::clearBootedModels();
        TaxonomyModels::clearColumnExistsCache();

        $this->createBaseSchema();
    }

    protected function createBaseSchema(): void
    {
        Schema::create('taxonomies', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->string('type');
            $table->string('context')->nullable();
            $table->string('input_type')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_filterable')->default(false);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_multiple')->default(false);
            $table->boolean('show_on_detail')->default(false);
            $table->boolean('show_on_card')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['type', 'slug']);
        });

        Schema::create('taxonomy_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('taxonomy_id')->constrained('taxonomies')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['taxonomy_id', 'slug']);
        });

        Schema::create('taxonomyables', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('taxonomy_item_id')->constrained('taxonomy_items')->cascadeOnDelete();
            $table->unsignedBigInteger('taxonomyable_id');
            $table->string('taxonomyable_type');
            $table->timestamps();
            $table->unique(['taxonomy_item_id', 'taxonomyable_type', 'taxonomyable_id']);
        });

        Schema::create('posts', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->timestamps();
        });

        Schema::create('cars', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->string('title');
            $table->string('slug')->unique();
            $table->unsignedInteger('price');
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('mileage');
            $table->unsignedSmallInteger('power')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
        });
    }

    protected function enableTeamTenancy(
        bool $withPivotTenantColumn = true,
        bool $withTenantAwarePivotUnique = true,
        array $configOverrides = []
    ): void {
        Schema::dropIfExists('taxonomyables');
        Schema::dropIfExists('taxonomy_items');
        Schema::dropIfExists('taxonomies');

        Schema::create('taxonomies', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->string('type');
            $table->string('context')->nullable();
            $table->string('input_type')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_filterable')->default(false);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_multiple')->default(false);
            $table->boolean('show_on_detail')->default(false);
            $table->boolean('show_on_card')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('team_id')->nullable()->index();
            $table->timestamps();
            $table->unique(['team_id', 'type', 'slug'], 'taxonomies_team_type_slug_unique');
            $table->index(['team_id', 'type', 'name'], 'taxonomies_team_type_name_index');
        });

        Schema::create('taxonomy_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('taxonomy_id')->constrained('taxonomies')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('team_id')->nullable()->index();
            $table->timestamps();
            $table->unique(['taxonomy_id', 'slug'], 'taxonomy_items_taxonomy_slug_unique');
            $table->index(['team_id', 'taxonomy_id', 'position'], 'taxonomy_items_team_taxonomy_position_index');
        });

        Schema::create('taxonomyables', function (Blueprint $table) use ($withPivotTenantColumn, $withTenantAwarePivotUnique): void {
            $table->id();
            $table->foreignId('taxonomy_item_id')->constrained('taxonomy_items')->cascadeOnDelete();
            $table->unsignedBigInteger('taxonomyable_id');
            $table->string('taxonomyable_type');

            if ($withPivotTenantColumn) {
                $table->unsignedBigInteger('team_id')->nullable()->index();
            }

            $table->timestamps();
            $table->index(['taxonomyable_type', 'taxonomyable_id'], 'taxonomyables_morph_index');

            if ($withPivotTenantColumn && $withTenantAwarePivotUnique) {
                $table->unique(
                    ['taxonomy_item_id', 'taxonomyable_type', 'taxonomyable_id', 'team_id'],
                    'taxonomyables_unique'
                );
            } else {
                $table->unique(
                    ['taxonomy_item_id', 'taxonomyable_type', 'taxonomyable_id'],
                    'taxonomyables_unique'
                );
            }
        });

        config()->set('taxonomy.tenancy.enabled', true);
        config()->set('taxonomy.tenancy.column', 'team_id');
        config()->set('taxonomy.tenancy.apply_global_scope', true);
        config()->set('taxonomy.tenancy.fail_when_unresolved', true);
        config()->set('taxonomy.tenancy.require_pivot_tenant_column', $withPivotTenantColumn);

        foreach ($configOverrides as $key => $value) {
            config()->set("taxonomy.tenancy.{$key}", $value);
        }

        TaxonomyModels::clearColumnExistsCache();
    }
}
