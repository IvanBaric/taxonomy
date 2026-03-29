<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('taxonomies', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->string('type');
            $table->text('description')->nullable();
            $table->boolean('is_filterable')->default(false);
            $table->boolean('is_multiple')->default(false);
            $table->timestamps();

            $table->unique(['type', 'slug'], 'taxonomies_type_slug_unique');
            $table->index(['type', 'name'], 'taxonomies_type_name_index');
        });

        Schema::create('taxonomy_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('taxonomy_id')->constrained('taxonomies')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['taxonomy_id', 'slug'], 'taxonomy_items_taxonomy_slug_unique');
            $table->index(['taxonomy_id', 'position'], 'taxonomy_items_taxonomy_position_index');
        });

        Schema::create('taxonomyables', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('taxonomy_item_id')->constrained('taxonomy_items')->cascadeOnDelete();
            $table->unsignedBigInteger('taxonomyable_id');
            $table->string('taxonomyable_type');
            $table->timestamps();

            $table->index(['taxonomyable_type', 'taxonomyable_id'], 'taxonomyables_morph_index');
            $table->unique(['taxonomy_item_id', 'taxonomyable_type', 'taxonomyable_id'], 'taxonomyables_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taxonomyables');
        Schema::dropIfExists('taxonomy_items');
        Schema::dropIfExists('taxonomies');
    }
};
