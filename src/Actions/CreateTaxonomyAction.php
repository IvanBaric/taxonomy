<?php

declare(strict_types=1);

namespace IvanBaric\Taxonomy\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use IvanBaric\Corexis\Data\ActionResult;
use IvanBaric\Taxonomy\Events\TaxonomyCreated;
use IvanBaric\Taxonomy\Models\Taxonomy;
use IvanBaric\Taxonomy\Support\TaxonomyModels;

final class CreateTaxonomyAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(array $attributes): ActionResult
    {
        if ($result = corexis_authorization_result('taxonomy.create')) {
            return $result;
        }

        $validator = Validator::make($attributes, [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:255'],
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

        /** @var class-string<Taxonomy> $modelClass */
        $modelClass = TaxonomyModels::taxonomy();

        /** @var Taxonomy $taxonomy */
        $taxonomy = DB::transaction(static fn (): Taxonomy => $modelClass::query()->create($validator->validated()));

        event(new TaxonomyCreated($taxonomy->refresh()));

        return ActionResult::success(
            message: __('Taksonomija je kreirana.'),
            data: $taxonomy,
        );
    }
}
