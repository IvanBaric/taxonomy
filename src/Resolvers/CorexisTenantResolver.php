<?php

declare(strict_types=1);

namespace IvanBaric\Taxonomy\Resolvers;

use IvanBaric\Corexis\Contracts\TenantResolver as CorexisTenantResolverContract;
use IvanBaric\Taxonomy\Contracts\TenantResolver;

final readonly class CorexisTenantResolver implements TenantResolver
{
    public function __construct(
        private CorexisTenantResolverContract $resolver,
    ) {}

    public function resolve(): int|string|null
    {
        if (! (bool) config('taxonomy.tenancy.enabled', false) || ! $this->resolver->enabled()) {
            return null;
        }

        return $this->resolver->id();
    }
}
