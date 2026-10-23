<?php

declare(strict_types=1);

namespace App\Outbound\Infrastructure\Http\Input;

use App\Outbound\Application\RegisterOrder\RegisterOrderLine;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Reads the lines of an order from the request body. A line of the wrong shape makes the request
 * unreadable (400), like any other field of the wrong type. Whether the values make sense is the
 * domain's call.
 */
final class RegisterOrderLines
{
    /**
     * @param array<array-key, mixed> $lines
     *
     * @return list<RegisterOrderLine>
     */
    public static function from(array $lines): array
    {
        $read = [];
        foreach (array_values($lines) as $position => $line) {
            if (!\is_array($line) || !\is_string($line['productId'] ?? null) || !\is_int($line['units'] ?? null)) {
                throw new BadRequestHttpException(\sprintf('Line %d of the order must be an object with a text "productId" and a whole number "units".', $position + 1));
            }

            $read[] = new RegisterOrderLine($line['productId'], $line['units']);
        }

        return $read;
    }
}
