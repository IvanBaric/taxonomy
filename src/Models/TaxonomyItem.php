<?php

declare(strict_types=1);

namespace IvanBaric\Taxonomy\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use IvanBaric\Corexis\Concerns\BelongsToTenant;
use IvanBaric\Corexis\Concerns\HasLockVersion;
use IvanBaric\Corexis\Concerns\HasUniqueSlug;
use IvanBaric\Corexis\Concerns\HasUuid;
use IvanBaric\Taxonomy\Support\TaxonomyConfigResolver;
use IvanBaric\Taxonomy\Support\TaxonomyModels;

class TaxonomyItem extends Model
{
    use BelongsToTenant, HasLockVersion, HasUniqueSlug, HasUuid;

    protected $fillable = [
        'taxonomy_id',
        'uuid',
        'name',
        'slug',
        'description',
        'meta',
        'position',
    ];

    protected $casts = [
        'id' => 'int',
        'meta' => 'array',
        'position' => 'int',
        'lock_version' => 'int',
    ];

    protected $attributes = [
        'position' => 0,
    ];

    public function getTable(): string
    {
        return TaxonomyConfigResolver::taxonomyItemsTable();
    }

    public function taxonomy(): BelongsTo
    {
        return $this->belongsTo(TaxonomyModels::taxonomy(), 'taxonomy_id');
    }

    public function scopeForTaxonomy(Builder $query, Model|int $taxonomy): Builder
    {
        $taxonomyId = $taxonomy instanceof Model
            ? (int) $taxonomy->getKey()
            : (int) $taxonomy;

        return $query->where('taxonomy_id', $taxonomyId);
    }

    public function scopeForType(Builder $query, string $type): Builder
    {
        return $query->whereHas('taxonomy', fn (Builder $taxonomyQuery) => $taxonomyQuery->where('type', $type));
    }

    public function scopeForSlug(Builder $query, string $slug): Builder
    {
        return $query->where('slug', Str::slug($slug));
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('position')->orderBy('name');
    }

    public function slugSource(): string
    {
        return trim((string) $this->getAttribute('name')) ?: 'item';
    }

    /** @return array<string, int|string|null> */
    public function uniqueSlugScope(): array
    {
        $tenantColumn = $this->getTenantColumn();

        return [
            $tenantColumn => $this->getAttribute($tenantColumn),
            'taxonomy_id' => $this->getAttribute('taxonomy_id'),
        ];
    }
}
