<?php

declare(strict_types=1);

namespace IvanBaric\Taxonomy\Exceptions;

use RuntimeException;

class UnresolvedTenantException extends RuntimeException
{
    public static function forCreate(): self
    {
        return new self('Cannot create taxonomy records because the current tenant could not be resolved.');
    }

    public static function forAttach(): self
    {
        return new self('Cannot attach taxonomy items because the current tenant could not be resolved.');
    }

    public static function forSync(): self
    {
        return new self('Cannot sync taxonomy items because the current tenant could not be resolved.');
    }

    public static function forDetach(): self
    {
        return new self('Cannot detach taxonomy items because the current tenant could not be resolved.');
    }
}
