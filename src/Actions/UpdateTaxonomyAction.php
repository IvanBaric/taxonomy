<?php

declare(strict_types=1);

namespace IvanBaric\Taxonomy\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use IvanBaric\Corexis\Concerns\UsesOptimisticLocking;
use IvanBaric\Corexis\Data\ActionResult;
use IvanBaric\Taxonomy\Events\TaxonomyUpdated;
use IvanBaric\Taxonomy\Models\Taxonomy;

final class UpdateTaxonomyAction
{
    use UsesOptimisticLocking;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Taxonomy $taxonomy, array $attributes): ActionResult
    {
        if ($result = corexis_authorization_result('taxonomy.update', $taxonomy)) {
            return $result;
        }

        $validator = Validator::make($attributes, [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'type' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_filterable' => ['sometimes', 'boolean'],
            'is_multiple' => ['sometimes', 'boolean'],
            'lock_version' => ['nullable', 'integer', 'min:0'],
        ]);

        if ($validator->fails()) {
            return ActionResult::error(
                message: __('Provjerite podatke taksonomije i pokušajte ponovno.'),
                code: 'validation_failed',
                errors: $validator->errors()->toArray(),
            );
        }

        $validated = $validator->validated();
        $expectedLockVersion = $this->pullExpectedLockVersion($validated);

        $saved = DB::transaction(function () use ($taxonomy, $validated, $expectedLockVersion): bool {
            return $this->saveWithOptimisticLock($taxonomy, $validated, $expectedLockVersion);
        });

        if (! $saved) {
            return $this->staleModelResult();
        }

        event(new TaxonomyUpdated($taxonomy->refresh()));

        return ActionResult::success(
            message: __('Taksonomija je ažurirana.'),
            data: $taxonomy,
        );
    }
}
