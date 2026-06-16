<?php

declare(strict_types=1);

namespace IvanBaric\Taxonomy\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use IvanBaric\Corexis\Contracts\Events\DomainEvent;
use IvanBaric\Taxonomy\Models\Taxonomy;

final class TaxonomyUpdated implements DomainEvent, ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Taxonomy $taxonomy,
    ) {}
}
