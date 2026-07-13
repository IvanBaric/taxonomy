<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use IvanBaric\Taxonomy\Support\TaxonomyConfigResolver;

return new class extends Migration
{
    public function up(): void
    {
        $tenantColumn = (string) config('corexis.tenancy.id_column', 'team_id');
        $taxonomies = TaxonomyConfigResolver::taxonomiesTable();
        $taxonomyItems = TaxonomyConfigResolver::taxonomyItemsTable();
        $taxonomyables = TaxonomyConfigResolver::taxonomyablesTable();

        if (! Schema::hasTable($taxonomies) || ! Schema::hasTable($taxonomyItems) || ! Schema::hasTable($taxonomyables)) {
            return;
        }

        Schema::table($taxonomies, function (Blueprint $table) use ($tenantColumn, $taxonomies): void {
            if (! Schema::hasColumn($taxonomies, $tenantColumn)) {
                $table->unsignedBigInteger($tenantColumn)->nullable()->index()->after('id');
            }

            if ($this->hasIndex($taxonomies, 'taxonomies_type_slug_unique')) {
                $table->dropUnique('taxonomies_type_slug_unique');
            }

            if (! $this->hasIndex($taxonomies, $this->taxonomyUniqueIndexName($tenantColumn))) {
                $table->unique([$tenantColumn, 'type', 'slug'], $this->taxonomyUniqueIndexName($tenantColumn));
            }
        });

        Schema::table($taxonomyItems, function (Blueprint $table) use ($tenantColumn, $taxonomyItems): void {
            if (! Schema::hasColumn($taxonomyItems, $tenantColumn)) {
                $table->unsignedBigInteger($tenantColumn)->nullable()->index()->after('id');
            }
        });

        Schema::table($taxonomyables, function (Blueprint $table) use ($tenantColumn, $taxonomyables): void {
            if (! Schema::hasColumn($taxonomyables, $tenantColumn)) {
                $table->unsignedBigInteger($tenantColumn)->nullable()->index()->after('id');
            }
        });

        $this->replaceTaxonomyablesUniqueIndex([
            'taxonomy_item_id',
            'taxonomyable_type',
            'taxonomyable_id',
            $tenantColumn,
        ]);
    }

    public function down(): void
    {
        $tenantColumn = (string) config('corexis.tenancy.id_column', 'team_id');
        $taxonomies = TaxonomyConfigResolver::taxonomiesTable();
        $taxonomyItems = TaxonomyConfigResolver::taxonomyItemsTable();
        $taxonomyables = TaxonomyConfigResolver::taxonomyablesTable();

        if (! Schema::hasTable($taxonomies) || ! Schema::hasTable($taxonomyItems) || ! Schema::hasTable($taxonomyables)) {
            return;
        }

        if (Schema::hasColumn($taxonomyables, $tenantColumn)) {
            $this->replaceTaxonomyablesUniqueIndex([
                'taxonomy_item_id',
                'taxonomyable_type',
                'taxonomyable_id',
            ]);
        }

        Schema::table($taxonomyables, function (Blueprint $table) use ($tenantColumn, $taxonomyables): void {
            if (Schema::hasColumn($taxonomyables, $tenantColumn)) {
                $table->dropColumn($tenantColumn);
            }
        });

        Schema::table($taxonomyItems, function (Blueprint $table) use ($tenantColumn, $taxonomyItems): void {
            if (Schema::hasColumn($taxonomyItems, $tenantColumn)) {
                $table->dropColumn($tenantColumn);
            }
        });

        Schema::table($taxonomies, function (Blueprint $table) use ($tenantColumn, $taxonomies): void {
            if (Schema::hasColumn($taxonomies, $tenantColumn)) {
                if ($this->hasIndex($taxonomies, $this->taxonomyUniqueIndexName($tenantColumn))) {
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

    /**
     * MySQL can use taxonomyables_unique as the supporting index for the
     * taxonomy_item_id foreign key, so changing it requires recreating the FK.
     */
    private function replaceTaxonomyablesUniqueIndex(array $columns): void
    {
        $taxonomyables = TaxonomyConfigResolver::taxonomyablesTable();

        if ($this->hasIndexWithColumns($taxonomyables, 'taxonomyables_unique', $columns, unique: true)) {
            $this->ensureTaxonomyablesForeignKey();

            return;
        }

        $hasForeignKey = $this->hasTaxonomyablesForeignKey();
        $foreignKeyName = $this->taxonomyablesForeignKeyName();
        $shouldRecreateForeignKey = $hasForeignKey
            && $foreignKeyName !== null
            && $this->mustDropForeignKeyBeforeIndexChange();

        if ($shouldRecreateForeignKey) {
            Schema::table($taxonomyables, function (Blueprint $table) use ($foreignKeyName): void {
                $table->dropForeign($foreignKeyName);
            });
        }

        Schema::table($taxonomyables, function (Blueprint $table) use ($taxonomyables): void {
            if ($this->hasIndex($taxonomyables, 'taxonomyables_unique')) {
                $table->dropUnique('taxonomyables_unique');
            }
        });

        Schema::table($taxonomyables, function (Blueprint $table) use ($columns, $taxonomyables): void {
            if (! $this->hasIndex($taxonomyables, 'taxonomyables_unique')) {
                $table->unique($columns, 'taxonomyables_unique');
            }
        });

        if ($shouldRecreateForeignKey || ! $hasForeignKey) {
            $this->ensureTaxonomyablesForeignKey();
        }
    }

    private function ensureTaxonomyablesForeignKey(): void
    {
        $taxonomyItems = TaxonomyConfigResolver::taxonomyItemsTable();
        $taxonomyables = TaxonomyConfigResolver::taxonomyablesTable();

        if ($this->hasTaxonomyablesForeignKey() || ! Schema::hasTable($taxonomyItems) || $this->usesSqlite()) {
            return;
        }

        Schema::table($taxonomyables, function (Blueprint $table) use ($taxonomyItems): void {
            $table
                ->foreign('taxonomy_item_id', 'taxonomyables_taxonomy_item_id_foreign')
                ->references('id')
                ->on($taxonomyItems)
                ->cascadeOnDelete();
        });
    }

    private function taxonomyablesForeignKeyName(): ?string
    {
        $foreignKey = $this->taxonomyablesForeignKey();

        if (! is_array($foreignKey)) {
            return null;
        }

        $name = $foreignKey['name'] ?? null;

        return is_string($name) && $name !== '' ? $name : null;
    }

    private function hasTaxonomyablesForeignKey(): bool
    {
        return $this->taxonomyablesForeignKey() !== null;
    }

    private function taxonomyablesForeignKey(): ?array
    {
        $taxonomyables = TaxonomyConfigResolver::taxonomyablesTable();
        $taxonomyItems = TaxonomyConfigResolver::taxonomyItemsTable();

        return collect(Schema::getForeignKeys($taxonomyables))
            ->first(function (array $definition) use ($taxonomyItems): bool {
                return ($definition['columns'] ?? []) === ['taxonomy_item_id']
                    && ($definition['foreign_table'] ?? null) === $taxonomyItems;
            });
    }

    private function hasIndexWithColumns(string $table, string $index, array $columns, bool $unique = false): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(function (array $definition) use ($index, $columns, $unique): bool {
                return ($definition['name'] ?? null) === $index
                    && array_values($definition['columns'] ?? []) === array_values($columns)
                    && (! $unique || (bool) ($definition['unique'] ?? false));
            });
    }

    private function mustDropForeignKeyBeforeIndexChange(): bool
    {
        return in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true);
    }

    private function usesSqlite(): bool
    {
        return Schema::getConnection()->getDriverName() === 'sqlite';
    }
};
