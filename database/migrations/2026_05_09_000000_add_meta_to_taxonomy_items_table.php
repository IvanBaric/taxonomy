<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use IvanBaric\Taxonomy\Support\TaxonomyConfigResolver;

return new class extends Migration
{
    public function up(): void
    {
        $taxonomyItems = TaxonomyConfigResolver::taxonomyItemsTable();

        Schema::table($taxonomyItems, function (Blueprint $table) use ($taxonomyItems): void {
            if (! Schema::hasColumn($taxonomyItems, 'meta')) {
                $table->json('meta')->nullable()->after('description');
            }
        });
    }

    public function down(): void
    {
        $taxonomyItems = TaxonomyConfigResolver::taxonomyItemsTable();

        Schema::table($taxonomyItems, function (Blueprint $table) use ($taxonomyItems): void {
            if (Schema::hasColumn($taxonomyItems, 'meta')) {
                $table->dropColumn('meta');
            }
        });
    }
};
