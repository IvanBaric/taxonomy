<?php

declare(strict_types=1);

namespace IvanBaric\Taxonomy\Tests\Fixtures\Support;

use IvanBaric\Taxonomy\Contracts\TenantResolver;

class StaticTenantResolver implements TenantResolver
{
    public static int|string|null $tenantKey = null;

    public function resolve(): int|string|null
    {
        return self::$tenantKey;
    }
}
