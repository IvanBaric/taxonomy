<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use IvanBaric\Taxonomy\Support\TaxonomyConfigResolver;

return new class extends Migration
{
    public function up(): void
    {
        $taxonomyItems = TaxonomyConfigResolver::taxonomyItemsTable();

        if (! Schema::hasTable($taxonomyItems)) {
            return;
        }

        if (! Schema::hasColumn($taxonomyItems, 'uuid')) {
            Schema::table($taxonomyItems, function (Blueprint $table): void {
                $table->uuid('uuid')->nullable()->after('id');
            });
        }

        DB::table($taxonomyItems)
            ->whereNull('uuid')
            ->orWhere('uuid', '')
            ->orderBy('id')
            ->select(['id'])
            ->chunkById(100, function ($items) use ($taxonomyItems): void {
                foreach ($items as $item) {
                    DB::table($taxonomyItems)
                        ->where('id', $item->id)
                        ->update(['uuid' => (string) Str::uuid()]);
                }
            });

        if (! $this->hasIndex($taxonomyItems, 'taxonomy_items_uuid_unique')) {
            Schema::table($taxonomyItems, function (Blueprint $table): void {
                $table->unique('uuid', 'taxonomy_items_uuid_unique');
            });
        }
    }

    public function down(): void
    {
        $taxonomyItems = TaxonomyConfigResolver::taxonomyItemsTable();

        if (! Schema::hasTable($taxonomyItems) || ! Schema::hasColumn($taxonomyItems, 'uuid')) {
            return;
        }

        Schema::table($taxonomyItems, function (Blueprint $table) use ($taxonomyItems): void {
            if ($this->hasIndex($taxonomyItems, 'taxonomy_items_uuid_unique')) {
                $table->dropUnique('taxonomy_items_uuid_unique');
            }

            $table->dropColumn('uuid');
        });
    }

    private function hasIndex(string $table, string $index): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn (array $definition): bool => ($definition['name'] ?? null) === $index);
    }
};
