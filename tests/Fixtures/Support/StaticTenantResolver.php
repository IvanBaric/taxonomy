<?php

declare(strict_types=1);

namespace IvanBaric\Taxonomy\Tests\Fixtures\Support;

use IvanBaric\Corexis\Contracts\TenantResolver;

class StaticTenantResolver implements TenantResolver
{
    public static int|string|null $tenantKey = null;

    public function enabled(): bool
    {
        return (bool) config('corexis.tenancy.enabled', false);
    }

    public function current(): mixed
    {
        return self::$tenantKey;
    }

    public function id(): int|string|null
    {
        return self::$tenantKey;
    }

    public function uuid(): ?string
    {
        return null;
    }

    public function type(): ?string
    {
        return 'team';
    }
}
