<?php

declare(strict_types=1);

namespace IvanBaric\Taxonomy\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use IvanBaric\Corexis\Concerns\BelongsToTenant;
use IvanBaric\Corexis\Concerns\HasLockVersion;
use IvanBaric\Corexis\Concerns\HasUniqueSlug;
use IvanBaric\Corexis\Concerns\HasUuid;
use IvanBaric\Taxonomy\Support\TaxonomyConfigResolver;
use IvanBaric\Taxonomy\Support\TaxonomyModels;

class Taxonomy extends Model
{
    use BelongsToTenant, HasLockVersion, HasUniqueSlug, HasUuid;

    protected $fillable = [
        'name',
        'uuid',
        'slug',
        'type',
        'description',
        'is_filterable',
        'is_multiple',
    ];

    protected $casts = [
        'id' => 'int',
        'is_filterable' => 'bool',
        'is_multiple' => 'bool',
        'lock_version' => 'int',
    ];

    protected $attributes = [
        'is_filterable' => false,
        'is_multiple' => false,
    ];

    public function getTable(): string
    {
        return TaxonomyConfigResolver::taxonomiesTable();
    }

    public function items(): HasMany
    {
        return $this->hasMany(TaxonomyModels::taxonomyItem(), 'taxonomy_id');
    }

    public function scopeForType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeForSlug(Builder $query, string $slug): Builder
    {
        return $query->where('slug', Str::slug($slug));
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('type')->orderBy('name');
    }

    public function slugSource(): string
    {
        return trim((string) $this->getAttribute('name')) ?: 'taxonomy';
    }

    /** @return array<string, int|string|null> */
    public function uniqueSlugScope(): array
    {
        $tenantColumn = $this->getTenantColumn();

        return [
            $tenantColumn => $this->getAttribute($tenantColumn),
            'type' => $this->getAttribute('type'),
        ];
    }
}
