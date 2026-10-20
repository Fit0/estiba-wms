<?php

declare(strict_types=1);

namespace App\Inventory\Domain;

use App\Inventory\Domain\Exception\InvalidMovementText;

/**
 * The checks shared by the free texts of a movement.
 *
 * @internal
 */
final class MovementText
{
    /**
     * @param 'reference'|'reason' $field
     *
     * @throws InvalidMovementText
     */
    public static function clean(string $field, string $value, int $maxLength): string
    {
        $trimmed = trim($value);
        $length = mb_strlen($trimmed);

        if ($length < 1 || $length > $maxLength || 1 === preg_match('/\p{Cc}/u', $trimmed)) {
            throw InvalidMovementText::forField($field, $maxLength);
        }

        return $trimmed;
    }
}
