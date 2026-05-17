<?php

declare(strict_types=1);

namespace IvanBaric\Taxonomy\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use IvanBaric\Taxonomy\Exceptions\InvalidTaxonomyAssignmentException;
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

        $this->applyActiveConstraint($relation, TaxonomyModels::taxonomyItem(), 'taxonomy_item_column');
        $relation->whereHas('taxonomy', function (Builder $query): void {
            $this->applyActiveConstraint($query, TaxonomyModels::taxonomy(), 'taxonomy_column');
        });

        if (! TaxonomyModels::tenancyEnabled()) {
            return $relation;
        }

        $tenantKey = TaxonomyModels::resolveTenantKey();

        if ($tenantKey === null || $tenantKey === '') {
            return $relation->whereRaw('1 = 0');
        }

        if (! TaxonomyModels::requirePivotTenantColumn()) {
            return $relation;
        }

        $column = TaxonomyModels::tenantColumn();
        TaxonomyModels::assertPivotTenantColumnExists();

        return $relation->withPivot($column)->wherePivot($column, $tenantKey);
    }

    public function taxonomyItems(): MorphToMany
    {
        return $this->taxonomyItemsRelation();
    }

    public function taxonomy(string $type): MorphToMany
    {
        return $this->taxonomyItemsRelation()
            ->whereHas('taxonomy', fn (Builder $query) => $query->where('type', $type))
            ->orderBy('position')
            ->orderBy('name');
    }

    public function scopeWithTaxonomy(
        Builder $query,
        string $type,
        mixed $items,
        string $operator = 'any',
        bool $onlyFilterable = false
    ): Builder {
        $values = $this->normalizeTaxonomyFilterValues($items);

        if ($values === []) {
            return $query;
        }

        if ($onlyFilterable && ! $this->taxonomyTypeIsFilterable($type)) {
            return $query;
        }

        $operator = strtolower($operator) === 'all' ? 'all' : 'any';

        if ($operator === 'all') {
            foreach ($values as $value) {
                $query->whereHas('taxonomyItems', fn (Builder $itemQuery) => $this->applyTaxonomyItemFilter(
                    $itemQuery,
                    $type,
                    [$value],
                    $onlyFilterable
                ));
            }

            return $query;
        }

        return $query->whereHas('taxonomyItems', fn (Builder $itemQuery) => $this->applyTaxonomyItemFilter(
            $itemQuery,
            $type,
            $values,
            $onlyFilterable
        ));
    }

    public function scopeWithTaxonomyFilters(
        Builder $query,
        array $filters,
        string $defaultOperator = 'any',
        bool $onlyFilterable = true
    ): Builder {
        foreach ($filters as $type => $filter) {
            if (! is_string($type) || trim($type) === '') {
                continue;
            }

            [$items, $operator] = $this->parseTaxonomyFilter($filter, $defaultOperator);

            $query->withTaxonomy($type, $items, $operator, $onlyFilterable);
        }

        return $query;
    }

    public function attachTaxonomy(string $type, mixed $items): static
    {
        $tenantKey = $this->tenantKeyForWrite('attach');
        $resolution = $this->resolveItemIds($type, $items);
        $ids = $resolution['ids'];

        if ($ids === []) {
            return $this;
        }

        $this->taxonomyItemsRelationForWrite($tenantKey)->syncWithoutDetaching(
            $this->buildAttachPayload($ids, $tenantKey)
        );

        return $this;
    }

    public function detachTaxonomy(string $type, mixed $items = null): static
    {
        $tenantKey = $this->tenantKeyForWrite('detach');

        if ($items === null) {
            $currentIds = $this->taxonomy($type)->pluck('taxonomy_items.id')->all();

            if ($currentIds !== []) {
                $this->taxonomyItemsRelationForWrite($tenantKey)->detach($currentIds);
            }

            return $this;
        }

        $ids = $this->resolveItemIds($type, $items)['ids'];

        if ($ids !== []) {
            $this->taxonomyItemsRelationForWrite($tenantKey)->detach($ids);
        }

        return $this;
    }

    public function syncTaxonomy(string $type, mixed $items): static
    {
        $tenantKey = $this->tenantKeyForWrite('sync');
        $currentIds = $this->taxonomy($type)->pluck('taxonomy_items.id')->all();
        $resolution = $this->resolveItemIds($type, $items);

        if ($resolution['explicit_empty']) {
            if ($currentIds !== []) {
                $this->taxonomyItemsRelationForWrite($tenantKey)->detach($currentIds);
            }

            return $this;
        }

        if ($resolution['ids'] === [] && $resolution['invalid'] === []) {
            return $this;
        }

        if ($resolution['ids'] === [] && $resolution['invalid'] !== []) {
            return $this;
        }

        $targetIds = $resolution['ids'];

        $toAttach = array_values(array_diff($targetIds, $currentIds));
        $toDetach = array_values(array_diff($currentIds, $targetIds));

        if ($toAttach !== []) {
            $this->taxonomyItemsRelationForWrite($tenantKey)->syncWithoutDetaching(
                $this->buildAttachPayload($toAttach, $tenantKey)
            );
        }

        if ($toDetach !== []) {
            $this->taxonomyItemsRelationForWrite($tenantKey)->detach($toDetach);
        }

        return $this;
    }

    public function hasTaxonomy(string $type, mixed $item): bool
    {
        $ids = $this->resolveItemIds($type, $item, false)['ids'];

        if ($ids === []) {
            return false;
        }

        return $this->taxonomy($type)->whereIn('taxonomy_items.id', $ids)->exists();
    }

    /**
     * @return array{ids: array<int, int>, invalid: array<int, string>, explicit_empty: bool}
     */
    protected function resolveItemIds(string $type, mixed $items, bool $throwOnInvalid = true): array
    {
        $explicitEmpty = $this->isExplicitEmptyInput($items);
        $flat = $this->flattenInputs($items);

        if ($flat === []) {
            return [
                'ids' => [],
                'invalid' => [],
                'explicit_empty' => $explicitEmpty,
            ];
        }

        $taxonomyItemModel = TaxonomyModels::taxonomyItem();
        $ids = [];
        $idCandidates = [];
        $slugCandidates = [];
        $invalid = [];

        foreach ($flat as $value) {
            if ($value instanceof Model && $value instanceof $taxonomyItemModel) {
                $key = $value->getKey();

                if ($key === null) {
                    $invalid[] = $this->describeInput($value);
                } else {
                    $idCandidates[(int) $key] = $this->describeInput($value);
                }

                continue;
            }

            if (is_int($value)) {
                if ($value > 0) {
                    $idCandidates[$value] = (string) $value;
                } else {
                    $invalid[] = (string) $value;
                }

                continue;
            }

            if (is_string($value)) {
                $trimmed = trim($value);

                if ($trimmed === '') {
                    continue;
                }

                if (ctype_digit($trimmed)) {
                    $integerValue = (int) $trimmed;

                    if ($integerValue > 0) {
                        $idCandidates[$integerValue] = $trimmed;
                    } else {
                        $invalid[] = $trimmed;
                    }
                } else {
                    $slugCandidates[Str::slug($trimmed)] = $trimmed;
                }

                continue;
            }

            if ($value !== null) {
                $invalid[] = $this->describeInput($value);
            }
        }

        $itemQuery = $this->taxonomyItemLookupQuery($type);

        if ($idCandidates !== []) {
            $validIds = (clone $itemQuery)
                ->whereIn('id', array_keys($idCandidates))
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all();

            $ids = array_merge($ids, $validIds);

            foreach (array_diff(array_keys($idCandidates), $validIds) as $invalidId) {
                $invalid[] = $idCandidates[$invalidId];
            }
        }

        if ($slugCandidates !== []) {
            $validSlugRows = (clone $itemQuery)
                ->whereIn('slug', array_keys($slugCandidates))
                ->get(['id', 'slug']);

            $ids = array_merge(
                $ids,
                $validSlugRows->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all()
            );

            $validSlugs = $validSlugRows->pluck('slug')->all();

            foreach (array_diff(array_keys($slugCandidates), $validSlugs) as $invalidSlug) {
                $invalid[] = $slugCandidates[$invalidSlug];
            }
        }

        $ids = array_values(array_unique(array_map('intval', $ids)));
        $invalid = array_values(array_unique($invalid));

        if ($throwOnInvalid && $invalid !== [] && TaxonomyModels::throwsOnInvalidAssignment()) {
            throw InvalidTaxonomyAssignmentException::forType($type, $invalid);
        }

        return [
            'ids' => $ids,
            'invalid' => $invalid,
            'explicit_empty' => $explicitEmpty,
        ];
    }

    protected function flattenInputs(mixed $items): array
    {
        if ($items === null) {
            return [];
        }

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

    protected function taxonomyItemsRelationForWrite(int|string|null $tenantKey = null): MorphToMany
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

        if (! TaxonomyModels::requirePivotTenantColumn()) {
            return $relation;
        }

        $tenantKey ??= $this->tenantKeyForWrite('attach');

        $column = TaxonomyModels::tenantColumn();
        TaxonomyModels::assertPivotTenantColumnExists();

        return $relation->withPivot($column)->wherePivot($column, $tenantKey);
    }

    protected function tenantKeyForWrite(string $operation): int|string|null
    {
        if (! TaxonomyModels::tenancyEnabled()) {
            return null;
        }

        $tenantKey = TaxonomyModels::resolveTenantKey();

        if ($tenantKey !== null && $tenantKey !== '') {
            return $tenantKey;
        }

        return match ($operation) {
            'attach' => throw UnresolvedTenantException::forAttach(),
            'detach' => throw UnresolvedTenantException::forDetach(),
            'sync' => throw UnresolvedTenantException::forSync(),
            default => throw UnresolvedTenantException::forAttach(),
        };
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, array<string, int|string>>
     */
    protected function buildAttachPayload(array $ids, int|string|null $tenantKey): array
    {
        $payload = [];

        foreach ($ids as $id) {
            $payload[$id] = [];
        }

        if (! TaxonomyModels::tenancyEnabled() || ! TaxonomyModels::requirePivotTenantColumn()) {
            return $payload;
        }

        $column = TaxonomyModels::tenantColumn();

        foreach ($ids as $id) {
            $payload[$id] = [$column => $tenantKey];
        }

        return $payload;
    }

    protected function taxonomyItemLookupQuery(string $type): Builder
    {
        $taxonomyItemModel = TaxonomyModels::taxonomyItem();
        $query = $taxonomyItemModel::query()
            ->whereHas('taxonomy', function (Builder $taxonomyQuery) use ($type): void {
                $taxonomyQuery->where('type', $type);
                $this->applyActiveConstraint($taxonomyQuery, TaxonomyModels::taxonomy(), 'taxonomy_column');
            });

        $this->applyActiveConstraint($query, $taxonomyItemModel, 'taxonomy_item_column');

        if (! TaxonomyModels::tenancyEnabled()) {
            return $query;
        }

        $tenantKey = TaxonomyModels::resolveTenantKey();

        if ($tenantKey === null || $tenantKey === '') {
            return $query->whereRaw('1 = 0');
        }

        $column = TaxonomyModels::tenantColumn();
        TaxonomyModels::assertTenantColumnExists((new $taxonomyItemModel)->getTable());

        return $query->where($column, $tenantKey);
    }

    protected function isExplicitEmptyInput(mixed $items): bool
    {
        if ($items instanceof Collection) {
            return $items->isEmpty();
        }

        return is_array($items) && $items === [];
    }

    protected function describeInput(mixed $value): string
    {
        if ($value instanceof Model) {
            $key = $value->getKey();

            return $key === null
                ? $value::class.'(unsaved)'
                : $value::class.'#'.$key;
        }

        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value) || is_bool($value)) {
            return (string) $value;
        }

        return get_debug_type($value);
    }

    protected function parseTaxonomyFilter(mixed $filter, string $defaultOperator): array
    {
        if (! is_array($filter)) {
            return [$filter, $defaultOperator];
        }

        $items = $filter['items'] ?? $filter['values'] ?? $filter['value'] ?? $filter;
        $operator = is_string($filter['operator'] ?? null)
            ? (string) $filter['operator']
            : $defaultOperator;

        return [$items, $operator];
    }

    protected function taxonomyTypeIsFilterable(string $type): bool
    {
        $taxonomyModel = TaxonomyModels::taxonomy();
        $query = $taxonomyModel::query()
            ->where('type', $type)
            ->where('is_filterable', true);

        $this->applyActiveConstraint($query, $taxonomyModel, 'taxonomy_column');

        return $query->exists();
    }

    protected function normalizeTaxonomyFilterValues(mixed $items): array
    {
        $values = [];
        $taxonomyItemModel = TaxonomyModels::taxonomyItem();

        foreach ($this->flattenInputs($items) as $item) {
            if ($item instanceof Model && $item instanceof $taxonomyItemModel) {
                $values[] = ['id' => (int) $item->getKey()];

                continue;
            }

            if (is_int($item)) {
                $values[] = ['id' => $item];

                continue;
            }

            if (is_string($item)) {
                $trimmed = trim($item);

                if ($trimmed !== '') {
                    $values[] = ['slug' => Str::slug($trimmed)];
                }
            }
        }

        return array_values(array_unique($values, SORT_REGULAR));
    }

    protected function applyTaxonomyItemFilter(
        Builder $query,
        string $type,
        array $values,
        bool $onlyFilterable
    ): void {
        $taxonomyItemModel = TaxonomyModels::taxonomyItem();
        $taxonomyItemTable = (new $taxonomyItemModel)->getTable();

        $this->applyActiveConstraint($query, $taxonomyItemModel, 'taxonomy_item_column');

        $query
            ->whereHas('taxonomy', function (Builder $taxonomyQuery) use ($type, $onlyFilterable): void {
                $taxonomyQuery
                    ->where('type', $type)
                    ->when($onlyFilterable, fn (Builder $query) => $query->where('is_filterable', true));

                $this->applyActiveConstraint($taxonomyQuery, TaxonomyModels::taxonomy(), 'taxonomy_column');
            })
            ->where(function (Builder $valueQuery) use ($values, $taxonomyItemTable): void {
                $ids = [];
                $slugs = [];

                foreach ($values as $value) {
                    if (isset($value['id'])) {
                        $ids[] = (int) $value['id'];
                    }

                    if (isset($value['slug'])) {
                        $slugs[] = (string) $value['slug'];
                    }
                }

                $valueQuery
                    ->when($ids !== [], fn (Builder $query) => $query->whereIn($taxonomyItemTable.'.id', array_unique($ids)))
                    ->when($slugs !== [], function (Builder $query) use ($slugs, $ids, $taxonomyItemTable): void {
                        $method = $ids === [] ? 'whereIn' : 'orWhereIn';

                        $query->{$method}($taxonomyItemTable.'.slug', array_unique($slugs));
                    });
            });
    }

    protected function applyActiveConstraint(Builder|MorphToMany $query, string $modelClass, string $columnConfigKey): void
    {
        $model = new $modelClass();

        if (method_exists($model, 'scopeActive')) {
            $query->active();

            return;
        }

        if (! (bool) config('taxonomy.activity.enabled', false)) {
            return;
        }

        $column = config('taxonomy.activity.'.$columnConfigKey);

        if (! is_string($column) || trim($column) === '') {
            return;
        }

        $query->where($model->getTable().'.'.$column, true);
    }
}
