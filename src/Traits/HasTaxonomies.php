<?php

declare(strict_types=1);

namespace IvanBaric\Taxonomy\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use IvanBaric\Taxonomy\Exceptions\UnresolvedTenantException;
use IvanBaric\Taxonomy\Support\TaxonomyModels;

trait HasTaxonomies
{
    protected function taxonomyItemsRelation(): MorphToMany
    {
        /** @var Model $this */
        $relation = $this->morphToMany(
            TaxonomyModels::taxonomyItem(),
            'taxonomyable',
            'taxonomyables',
            'taxonomyable_id',
            'taxonomy_item_id'
        )->withTimestamps();

        if (! TaxonomyModels::tenancyEnabled()) {
            return $relation;
        }

        $tenantKey = TaxonomyModels::resolveTenantKey();
        $column = TaxonomyModels::tenantColumn();

        $relation->withPivot($column);

        if ($tenantKey === null || $tenantKey === '') {
            return $relation->whereRaw('1 = 0');
        }

        return $relation->wherePivot($column, $tenantKey);
    }

    public function taxonomy(string $type): MorphToMany
    {
        return $this->taxonomyItemsRelation()
            ->whereHas('taxonomy', fn (Builder $query) => $query->where('type', $type))
            ->orderBy('position')
            ->orderBy('name');
    }

    public function attachTaxonomy(string $type, mixed $items): static
    {
        $ids = $this->resolveItemIds($type, $items);

        if ($ids === []) {
            return $this;
        }

        $attachPayload = [];

        if (TaxonomyModels::tenancyEnabled()) {
            $tenantKey = TaxonomyModels::resolveTenantKey();

            if ($tenantKey === null || $tenantKey === '') {
                throw UnresolvedTenantException::forAttach();
            }

            $column = TaxonomyModels::tenantColumn();

            foreach ($ids as $id) {
                $attachPayload[$id] = [$column => $tenantKey];
            }
        } else {
            foreach ($ids as $id) {
                $attachPayload[$id] = [];
            }
        }

        $this->taxonomyItemsRelation()->syncWithoutDetaching($attachPayload);

        return $this;
    }

    public function detachTaxonomy(string $type, mixed $items = null): static
    {
        if ($items === null) {
            $currentIds = $this->taxonomy($type)->pluck('taxonomy_items.id')->all();

            if ($currentIds !== []) {
                $this->taxonomyItemsRelation()->detach($currentIds);
            }

            return $this;
        }

        $ids = $this->resolveItemIds($type, $items);

        if ($ids !== []) {
            $this->taxonomyItemsRelation()->detach($ids);
        }

        return $this;
    }

    public function syncTaxonomy(string $type, mixed $items): static
    {
        $targetIds = $this->resolveItemIds($type, $items);
        $currentIds = $this->taxonomy($type)->pluck('taxonomy_items.id')->all();

        $toAttach = array_values(array_diff($targetIds, $currentIds));
        $toDetach = array_values(array_diff($currentIds, $targetIds));

        if ($toAttach !== []) {
            $this->attachTaxonomy($type, $toAttach);
        }

        if ($toDetach !== []) {
            $this->taxonomyItemsRelation()->detach($toDetach);
        }

        return $this;
    }

    public function hasTaxonomy(string $type, mixed $item): bool
    {
        $ids = $this->resolveItemIds($type, $item);

        if ($ids === []) {
            return false;
        }

        return $this->taxonomy($type)->whereIn('taxonomy_items.id', $ids)->exists();
    }

    protected function resolveItemIds(string $type, mixed $items): array
    {
        $flat = $this->flattenInputs($items);

        if ($flat === []) {
            return [];
        }

        $taxonomyItemModel = TaxonomyModels::taxonomyItem();
        $ids = [];
        $idCandidates = [];
        $slugCandidates = [];

        foreach ($flat as $value) {
            if ($value instanceof Model && $value instanceof $taxonomyItemModel) {
                $idCandidates[] = (int) $value->getKey();

                continue;
            }

            if (is_int($value)) {
                $idCandidates[] = $value;

                continue;
            }

            if (is_string($value)) {
                $trimmed = trim($value);

                if ($trimmed !== '') {
                    $slugCandidates[] = Str::slug($trimmed);
                }
            }
        }

        $itemQuery = $taxonomyItemModel::query()
            ->whereHas('taxonomy', fn (Builder $query) => $query->where('type', $type));

        if ($idCandidates !== []) {
            $ids = array_merge(
                $ids,
                (clone $itemQuery)->whereIn('id', array_unique($idCandidates))->pluck('id')->all()
            );
        }

        if ($slugCandidates !== []) {
            $ids = array_merge(
                $ids,
                (clone $itemQuery)->whereIn('slug', array_unique($slugCandidates))->pluck('id')->all()
            );
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }

    protected function flattenInputs(mixed $items): array
    {
        if ($items instanceof Collection) {
            $items = $items->all();
        }

        if (! is_array($items)) {
            return [$items];
        }

        $result = [];
        $stack = [$items];

        while ($stack !== []) {
            $current = array_pop($stack);

            foreach ($current as $value) {
                if ($value instanceof Collection) {
                    $stack[] = $value->all();
                } elseif (is_array($value)) {
                    $stack[] = $value;
                } else {
                    $result[] = $value;
                }
            }
        }

        return $result;
    }
}
