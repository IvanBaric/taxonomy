<?php

declare(strict_types=1);

namespace IvanBaric\Taxonomy\Exceptions;

use RuntimeException;

class MisconfiguredTenancyException extends RuntimeException
{
    public static function missingTenantColumn(string $table, string $column): self
    {
        return new self(
            "Taxonomy tenancy is enabled, but column [{$column}] is missing from table [{$table}]."
        );
    }

    public static function missingPivotTenantColumn(string $table, string $column): self
    {
        return new self(
            "Taxonomy tenancy requires pivot isolation, but column [{$column}] is missing from pivot table [{$table}]."
        );
    }
}
