<?php

declare(strict_types=1);

namespace IvanBaric\Taxonomy\Exceptions;

use InvalidArgumentException;

class InvalidTaxonomyAssignmentException extends InvalidArgumentException
{
    /**
     * @param array<int, string> $invalidReferences
     */
    public static function forType(string $type, array $invalidReferences): self
    {
        $invalidReferences = array_values(array_unique($invalidReferences));
        $preview = implode(', ', array_slice($invalidReferences, 0, 5));
        $remaining = count($invalidReferences) - 5;

        if ($remaining > 0) {
            $preview .= " (+{$remaining} more)";
        }

        return new self(
            "Invalid taxonomy assignment for [{$type}]. ".
            'These inputs do not resolve to items in that taxonomy: '.$preview.'.'
        );
    }
}
