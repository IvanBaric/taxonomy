<?php

declare(strict_types=1);

namespace IvanBaric\Taxonomy\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use IvanBaric\Corexis\Contracts\Events\DomainEvent;

final class TaxonomyItemsSynced implements DomainEvent, ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<int, int>  $attachedItemIds
     * @param  array<int, int>  $detachedItemIds
     * @param  array<int, int>  $currentItemIds
     */
    public function __construct(
        public readonly Model $model,
        public readonly string $type,
        public readonly array $attachedItemIds,
        public readonly array $detachedItemIds,
        public readonly array $currentItemIds,
    ) {}
}
