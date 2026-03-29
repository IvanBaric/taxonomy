<?php

declare(strict_types=1);

namespace IvanBaric\Taxonomy\Contracts;

interface TenantResolver
{
    public function resolve(): int|string|null;
}
