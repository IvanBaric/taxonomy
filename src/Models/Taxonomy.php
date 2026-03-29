<?php

declare(strict_types=1);

namespace IvanBaric\Taxonomy\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use IvanBaric\Taxonomy\Concerns\HasOptionalTenancy;
use IvanBaric\Taxonomy\Support\TaxonomyModels;

class Taxonomy extends Model
{
    use HasOptionalTenancy;

    protected $table = 'taxonomies';

    protected $fillable = [
        'name',
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
    ];

    protected $attributes = [
        'is_filterable' => false,
        'is_multiple' => false,
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (! isset($model->slug) || trim((string) $model->slug) === '') {
                $model->slug = static::generateUniqueSlug((string) $model->name, (string) $model->type, $model);
            } else {
                $model->slug = static::ensureUniqueSlug((string) $model->slug, (string) $model->type, null, $model);
            }
        });

        static::updating(function (self $model): void {
            if (! $model->isDirty('slug')) {
                return;
            }

            $slug = trim((string) $model->slug);

            $model->slug = $slug === ''
                ? static::ensureUniqueSlug((string) $model->name, (string) $model->type, $model->getKey(), $model)
                : static::ensureUniqueSlug($slug, (string) $model->type, $model->getKey(), $model);
        });
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

    protected static function generateUniqueSlug(string $name, string $type, ?self $model = null): string
    {
        $base = Str::slug($name) ?: 'taxonomy';

        return static::ensureUniqueSlug($base, $type, null, $model);
    }

    protected static function ensureUniqueSlug(string $slug, string $type, mixed $ignoreId = null, ?self $model = null): string
    {
        $base = Str::slug($slug) ?: 'taxonomy';
        $candidate = $base;
        $suffix = 1;

        while (static::slugExists($candidate, $type, $ignoreId, $model)) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    protected static function slugExists(string $slug, string $type, mixed $ignoreId = null, ?self $model = null): bool
    {
        $query = static::query()
            ->where('type', $type)
            ->where('slug', $slug);

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        if (TaxonomyModels::tenancyEnabled()) {
            $column = TaxonomyModels::tenantColumn();
            $tenantKey = $model?->getAttribute($column) ?? TaxonomyModels::resolveTenantKey();

            if ($tenantKey === null || $tenantKey === '') {
                return false;
            }

            $query->where($column, $tenantKey);
        }

        return $query->exists();
    }
}
