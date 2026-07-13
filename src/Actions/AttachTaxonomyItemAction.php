<?php

declare(strict_types=1);

namespace IvanBaric\Taxonomy\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use IvanBaric\Corexis\Data\ActionResult;
use IvanBaric\Corexis\Exceptions\TenantNotResolvedException;
use IvanBaric\Taxonomy\Events\TaxonomyItemAttached;
use IvanBaric\Taxonomy\Exceptions\InvalidTaxonomyAssignmentException;
use IvanBaric\Taxonomy\Support\TaxonomyAssignment;

final readonly class AttachTaxonomyItemAction
{
    public function __construct(private TaxonomyAssignment $assignments) {}

    public function handle(Model $model, string $type, mixed $items): ActionResult
    {
        if ($result = corexis_authorization_result('taxonomy.attach', $model)) {
            return $result;
        }

        if (! $this->assignments->supports($model)) {
            return ActionResult::error(
                message: __('Model ne podržava taksonomije.'),
                code: 'taxonomy_not_supported',
            );
        }

        try {
            [$lockedModel, $attached] = DB::transaction(function () use ($model, $type, $items): array {
                /** @var Model $lockedModel */
                $lockedModel = $model->newQuery()
                    ->whereKey($model->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $before = $this->assignments->itemIds($lockedModel, $type);
                $this->assignments->attach($lockedModel, $type, $items);
                $after = $this->assignments->itemIds($lockedModel, $type);

                return [$lockedModel, array_values(array_diff($after, $before))];
            });
        } catch (InvalidArgumentException|InvalidTaxonomyAssignmentException|TenantNotResolvedException $exception) {
            return ActionResult::error(
                message: $exception->getMessage(),
                code: 'taxonomy_attach_failed',
            );
        }

        event(new TaxonomyItemAttached($lockedModel, $type, $attached));

        return ActionResult::success(
            message: __('Taksonomske stavke su dodane.'),
            data: ['attached_item_ids' => $attached],
        );
    }
}
