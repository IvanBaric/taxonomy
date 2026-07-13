<?php

declare(strict_types=1);

namespace IvanBaric\Taxonomy\Support;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class TaxonomyAssignment
{
    public function supports(Model $model): bool
    {
        return method_exists($model, 'taxonomyItemIds')
            && method_exists($model, 'attachTaxonomy')
            && method_exists($model, 'detachTaxonomy')
            && method_exists($model, 'syncTaxonomy');
    }

    /** @return array<int, int> */
    public function itemIds(Model $model, string $type): array
    {
        if (! method_exists($model, 'taxonomyItemIds')) {
            throw new InvalidArgumentException('Model does not support taxonomies.');
        }

        $ids = $model->taxonomyItemIds($type);

        if (! is_array($ids)) {
            throw new InvalidArgumentException('Taxonomy item identifiers must be returned as an array.');
        }

        return array_values(array_map('intval', $ids));
    }

    public function attach(Model $model, string $type, mixed $items): void
    {
        if (! method_exists($model, 'attachTaxonomy')) {
            throw new InvalidArgumentException('Model does not support taxonomy attachment.');
        }

        $model->attachTaxonomy($type, $items);
    }

    public function detach(Model $model, string $type, mixed $items = null): void
    {
        if (! method_exists($model, 'detachTaxonomy')) {
            throw new InvalidArgumentException('Model does not support taxonomy detachment.');
        }

        $model->detachTaxonomy($type, $items);
    }

    public function sync(Model $model, string $type, mixed $items): void
    {
        if (! method_exists($model, 'syncTaxonomy')) {
            throw new InvalidArgumentException('Model does not support taxonomy synchronization.');
        }

        $model->syncTaxonomy($type, $items);
    }
}
