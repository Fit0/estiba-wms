<?php

declare(strict_types=1);

namespace App\Outbound\Infrastructure\Http\Input;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Reads the order identifiers of a wave from the request body. One that is not a text makes the
 * request unreadable (400), like any other field of the wrong type. Whether the orders exist and
 * may join is the domain's call.
 */
final class CreateWaveOrderIds
{
    /**
     * @param array<array-key, mixed> $orderIds
     *
     * @return list<string>
     */
    public static function from(array $orderIds): array
    {
        $read = [];
        foreach (array_values($orderIds) as $position => $orderId) {
            if (!\is_string($orderId)) {
                throw new BadRequestHttpException(\sprintf('Order %d of the wave must be the identifier of an order, as text.', $position + 1));
            }

            $read[] = $orderId;
        }

        return $read;
    }
}
