<?php

declare(strict_types=1);

namespace IvanBaric\Taxonomy\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use IvanBaric\Corexis\Data\ActionResult;
use IvanBaric\Taxonomy\Events\TaxonomyItemDetached;
use IvanBaric\Taxonomy\Exceptions\InvalidTaxonomyAssignmentException;
use IvanBaric\Taxonomy\Exceptions\UnresolvedTenantException;

final class DetachTaxonomyItemAction
{
    public function handle(Model $model, string $type, mixed $items = null): ActionResult
    {
        if ($result = corexis_authorization_result('taxonomy.attach', $model)) {
            return $result;
        }

        if (! method_exists($model, 'detachTaxonomy') || ! method_exists($model, 'taxonomy')) {
            return ActionResult::error(
                message: __('Model ne podržava taksonomije.'),
                code: 'taxonomy_not_supported',
            );
        }

        $before = $this->currentItemIds($model, $type);

        try {
            DB::transaction(static function () use ($model, $type, $items): void {
                $model->newQuery()
                    ->whereKey($model->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $model->detachTaxonomy($type, $items);
            });
        } catch (InvalidArgumentException|InvalidTaxonomyAssignmentException|UnresolvedTenantException $exception) {
            return ActionResult::error(
                message: $exception->getMessage(),
                code: 'taxonomy_detach_failed',
            );
        }

        $after = $this->currentItemIds($model, $type);
        $detached = array_values(array_diff($before, $after));

        event(new TaxonomyItemDetached($model, $type, $detached));

        return ActionResult::success(
            message: __('Taksonomske stavke su uklonjene.'),
            data: ['detached_item_ids' => $detached],
        );
    }

    /**
     * @return array<int, int>
     */
    private function currentItemIds(Model $model, string $type): array
    {
        return $model->taxonomy($type)
            ->pluck('taxonomy_items.id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }
}
