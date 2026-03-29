<?php

declare(strict_types=1);

namespace IvanBaric\Taxonomy\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Str;
use IvanBaric\Taxonomy\Support\TaxonomyModels;
use IvanBaric\Taxonomy\Traits\HasTaxonomies;

class Car extends Model
{
    use HasTaxonomies;

    protected $table = 'cars';

    protected $fillable = [
        'uuid',
        'title',
        'slug',
        'price',
        'year',
        'mileage',
        'power',
        'description',
        'is_visible',
    ];

    protected $casts = [
        'price' => 'int',
        'year' => 'int',
        'mileage' => 'int',
        'power' => 'int',
        'is_visible' => 'bool',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $car): void {
            $car->uuid ??= (string) Str::uuid();
            $car->slug ??= Str::slug($car->title);
        });
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_visible', true);
    }

    /**
     * Public base relation used for filtering and grouped display in real apps.
     * The package trait keeps its internal relation protected, so apps typically
     * add their own public relation like this.
     */
    public function taxonomyItems(): MorphToMany
    {
        return $this->morphToMany(
            TaxonomyModels::taxonomyItem(),
            'taxonomyable',
            'taxonomyables',
            'taxonomyable_id',
            'taxonomy_item_id'
        )->withTimestamps();
    }
}
