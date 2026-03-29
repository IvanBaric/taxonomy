<?php

declare(strict_types=1);

namespace IvanBaric\Taxonomy\Exceptions;

use RuntimeException;

class UnresolvedTenantException extends RuntimeException
{
    public static function forCreate(): self
    {
        return new self('Unable to resolve a tenant key for taxonomy write operation.');
    }

    public static function forAttach(): self
    {
        return new self('Unable to resolve a tenant key for taxonomy attachment write operation.');
    }
}
