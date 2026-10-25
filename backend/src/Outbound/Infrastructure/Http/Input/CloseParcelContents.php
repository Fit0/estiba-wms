<?php

declare(strict_types=1);

namespace App\Outbound\Infrastructure\Http\Input;

use App\Outbound\Application\CloseParcel\CloseParcelContent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Reads the contents of a parcel from the request body. One of the wrong shape makes the request
 * unreadable (400), like any other field of the wrong type. Whether the values make sense is the
 * domain's call.
 */
final class CloseParcelContents
{
    /**
     * @param array<array-key, mixed> $contents
     *
     * @return list<CloseParcelContent>
     */
    public static function from(array $contents): array
    {
        $read = [];
        foreach (array_values($contents) as $position => $content) {
            if (!\is_array($content) || !\is_string($content['productId'] ?? null) || !\is_int($content['units'] ?? null)) {
                throw new BadRequestHttpException(\sprintf('Item %d of the parcel must be an object with a text "productId" and a whole number "units".', $position + 1));
            }

            $read[] = new CloseParcelContent($content['productId'], $content['units']);
        }

        return $read;
    }
}
