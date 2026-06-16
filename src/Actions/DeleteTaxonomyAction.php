<?php

declare(strict_types=1);

namespace IvanBaric\Taxonomy\Actions;

use Illuminate\Support\Facades\DB;
use IvanBaric\Corexis\Data\ActionResult;
use IvanBaric\Taxonomy\Events\TaxonomyDeleted;
use IvanBaric\Taxonomy\Models\Taxonomy;

final class DeleteTaxonomyAction
{
    public function handle(Taxonomy $taxonomy): ActionResult
    {
        if ($result = corexis_authorization_result('taxonomy.delete', $taxonomy)) {
            return $result;
        }

        $taxonomyId = $taxonomy->getKey();
        $type = (string) $taxonomy->type;
        $slug = (string) $taxonomy->slug;

        DB::transaction(static function () use ($taxonomy): void {
            $taxonomy->delete();
        });

        event(new TaxonomyDeleted($taxonomyId, $type, $slug));

        return ActionResult::success(__('Taksonomija je obrisana.'));
    }
}
