<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tenantColumn = (string) config('taxonomy.tenancy.column', 'team_id');

        if (! Schema::hasTable('taxonomies') || ! Schema::hasTable('taxonomy_items') || ! Schema::hasTable('taxonomyables')) {
            return;
        }

        Schema::table('taxonomies', function (Blueprint $table) use ($tenantColumn): void {
            if (! Schema::hasColumn('taxonomies', $tenantColumn)) {
                $table->unsignedBigInteger($tenantColumn)->nullable()->index()->after('id');
            }

            if ($this->hasIndex('taxonomies', 'taxonomies_type_slug_unique')) {
                $table->dropUnique('taxonomies_type_slug_unique');
            }

            if (! $this->hasIndex('taxonomies', $this->taxonomyUniqueIndexName($tenantColumn))) {
                $table->unique([$tenantColumn, 'type', 'slug'], $this->taxonomyUniqueIndexName($tenantColumn));
            }
        });

        Schema::table('taxonomy_items', function (Blueprint $table) use ($tenantColumn): void {
            if (! Schema::hasColumn('taxonomy_items', $tenantColumn)) {
                $table->unsignedBigInteger($tenantColumn)->nullable()->index()->after('id');
            }
        });

        Schema::table('taxonomyables', function (Blueprint $table) use ($tenantColumn): void {
            if (! Schema::hasColumn('taxonomyables', $tenantColumn)) {
                $table->unsignedBigInteger($tenantColumn)->nullable()->index()->after('id');
            }

            if ($this->hasIndex('taxonomyables', 'taxonomyables_unique')) {
                $table->dropUnique('taxonomyables_unique');
            }

            $table->unique(['taxonomy_item_id', 'taxonomyable_type', 'taxonomyable_id', $tenantColumn], 'taxonomyables_unique');
        });
    }

    public function down(): void
    {
        $tenantColumn = (string) config('taxonomy.tenancy.column', 'team_id');

        if (! Schema::hasTable('taxonomies') || ! Schema::hasTable('taxonomy_items') || ! Schema::hasTable('taxonomyables')) {
            return;
        }

        Schema::table('taxonomyables', function (Blueprint $table) use ($tenantColumn): void {
            if (Schema::hasColumn('taxonomyables', $tenantColumn)) {
                $table->dropUnique('taxonomyables_unique');
                $table->dropColumn($tenantColumn);
                $table->unique(['taxonomy_item_id', 'taxonomyable_type', 'taxonomyable_id'], 'taxonomyables_unique');
            }
        });

        Schema::table('taxonomy_items', function (Blueprint $table) use ($tenantColumn): void {
            if (Schema::hasColumn('taxonomy_items', $tenantColumn)) {
                $table->dropColumn($tenantColumn);
            }
        });

        Schema::table('taxonomies', function (Blueprint $table) use ($tenantColumn): void {
            if (Schema::hasColumn('taxonomies', $tenantColumn)) {
                if ($this->hasIndex('taxonomies', $this->taxonomyUniqueIndexName($tenantColumn))) {
                    $table->dropUnique($this->taxonomyUniqueIndexName($tenantColumn));
                }

                $table->dropColumn($tenantColumn);
                $table->unique(['type', 'slug'], 'taxonomies_type_slug_unique');
            }
        });
    }

    private function taxonomyUniqueIndexName(string $tenantColumn): string
    {
        return 'taxonomies_'.$tenantColumn.'_type_slug_unique';
    }

    private function hasIndex(string $table, string $index): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn (array $definition): bool => ($definition['name'] ?? null) === $index);
    }
};
