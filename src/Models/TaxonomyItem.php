<?php

declare(strict_types=1);

namespace IvanBaric\Taxonomy\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Str;
use IvanBaric\Taxonomy\Concerns\HasOptionalTenancy;
use IvanBaric\Taxonomy\Support\TaxonomyModels;

class TaxonomyItem extends Model
{
    use HasOptionalTenancy;

    protected $table = 'taxonomy_items';

    protected $fillable = [
        'taxonomy_id',
        'name',
        'slug',
        'description',
        'position',
    ];

    protected $casts = [
        'id' => 'int',
        'position' => 'int',
    ];

    protected $attributes = [
        'position' => 0,
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (! isset($model->slug) || trim((string) $model->slug) === '') {
                $model->slug = static::generateUniqueSlug((string) $model->name, (int) $model->taxonomy_id);
            } else {
                $model->slug = static::ensureUniqueSlug((string) $model->slug, (int) $model->taxonomy_id);
            }
        });

        static::updating(function (self $model): void {
            if (! $model->isDirty('slug')) {
                return;
            }

            $slug = trim((string) $model->slug);

            $model->slug = $slug === ''
                ? static::ensureUniqueSlug((string) $model->name, (int) $model->taxonomy_id, $model->getKey())
                : static::ensureUniqueSlug($slug, (int) $model->taxonomy_id, $model->getKey());
        });
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

    public function models(): MorphToMany
    {
        return $this->morphToMany(Model::class, 'taxonomyable', 'taxonomyables', 'taxonomy_item_id', 'taxonomyable_id');
    }

    protected static function generateUniqueSlug(string $name, int $taxonomyId): string
    {
        $base = Str::slug($name) ?: 'item';

        return static::ensureUniqueSlug($base, $taxonomyId);
    }

    protected static function ensureUniqueSlug(string $slug, int $taxonomyId, mixed $ignoreId = null): string
    {
        $base = Str::slug($slug) ?: 'item';
        $candidate = $base;
        $suffix = 1;

        while (static::query()
            ->where('taxonomy_id', $taxonomyId)
            ->where('slug', $candidate)
            ->when($ignoreId !== null, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }
}
