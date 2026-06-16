<?php

declare(strict_types=1);

namespace IvanBaric\Taxonomy\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use IvanBaric\Corexis\Data\ActionResult;
use IvanBaric\Taxonomy\Events\TaxonomyItemAttached;
use IvanBaric\Taxonomy\Exceptions\InvalidTaxonomyAssignmentException;
use IvanBaric\Taxonomy\Exceptions\UnresolvedTenantException;

final class AttachTaxonomyItemAction
{
    public function handle(Model $model, string $type, mixed $items): ActionResult
    {
        if ($result = corexis_authorization_result('taxonomy.attach', $model)) {
            return $result;
        }

        if (! method_exists($model, 'attachTaxonomy') || ! method_exists($model, 'taxonomy')) {
            return ActionResult::error(
                message: __('Model ne podržava taksonomije.'),
                code: 'taxonomy_not_supported',
            );
        }

        $before = $this->currentItemIds($model, $type);

        try {
            DB::transaction(static function () use ($model, $type, $items): void {
                $model->attachTaxonomy($type, $items);
            });
        } catch (InvalidArgumentException|InvalidTaxonomyAssignmentException|UnresolvedTenantException $exception) {
            return ActionResult::error(
                message: $exception->getMessage(),
                code: 'taxonomy_attach_failed',
            );
        }

        $after = $this->currentItemIds($model, $type);
        $attached = array_values(array_diff($after, $before));

        event(new TaxonomyItemAttached($model, $type, $attached));

        return ActionResult::success(
            message: __('Taksonomske stavke su dodane.'),
            data: ['attached_item_ids' => $attached],
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
