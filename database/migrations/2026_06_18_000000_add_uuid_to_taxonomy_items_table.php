<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('taxonomy_items')) {
            return;
        }

        if (! Schema::hasColumn('taxonomy_items', 'uuid')) {
            Schema::table('taxonomy_items', function (Blueprint $table): void {
                $table->uuid('uuid')->nullable()->after('id');
            });
        }

        DB::table('taxonomy_items')
            ->whereNull('uuid')
            ->orWhere('uuid', '')
            ->orderBy('id')
            ->select(['id'])
            ->chunkById(100, function ($items): void {
                foreach ($items as $item) {
                    DB::table('taxonomy_items')
                        ->where('id', $item->id)
                        ->update(['uuid' => (string) Str::uuid()]);
                }
            });

        if (! $this->hasIndex('taxonomy_items', 'taxonomy_items_uuid_unique')) {
            Schema::table('taxonomy_items', function (Blueprint $table): void {
                $table->unique('uuid', 'taxonomy_items_uuid_unique');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('taxonomy_items') || ! Schema::hasColumn('taxonomy_items', 'uuid')) {
            return;
        }

        Schema::table('taxonomy_items', function (Blueprint $table): void {
            if ($this->hasIndex('taxonomy_items', 'taxonomy_items_uuid_unique')) {
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
