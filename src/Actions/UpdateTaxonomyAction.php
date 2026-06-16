<?php

declare(strict_types=1);

namespace IvanBaric\Taxonomy\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use IvanBaric\Corexis\Data\ActionResult;
use IvanBaric\Taxonomy\Events\TaxonomyUpdated;
use IvanBaric\Taxonomy\Models\Taxonomy;

final class UpdateTaxonomyAction
{
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
        ]);

        if ($validator->fails()) {
            return ActionResult::error(
                message: __('Provjerite podatke taksonomije i pokušajte ponovno.'),
                code: 'validation_failed',
                errors: $validator->errors()->toArray(),
            );
        }

        DB::transaction(static function () use ($taxonomy, $validator): void {
            $taxonomy->fill($validator->validated());
            $taxonomy->save();
        });

        event(new TaxonomyUpdated($taxonomy->refresh()));

        return ActionResult::success(
            message: __('Taksonomija je ažurirana.'),
            data: $taxonomy,
        );
    }
}
