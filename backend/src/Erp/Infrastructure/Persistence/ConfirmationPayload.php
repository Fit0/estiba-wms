<?php

declare(strict_types=1);

namespace App\Erp\Infrastructure\Persistence;

use App\Erp\Application\ShipmentConfirmation;
use App\Erp\Application\ShippedLine;

/**
 * A confirmation as the JSON that the outbox keeps. What was queued is what is sent, even if the code that read it
 * changed in between, so it carries the instant with its offset and the lines by SKU.
 */
final class ConfirmationPayload
{
    private function __construct()
    {
    }

    public static function encode(ShipmentConfirmation $confirmation): string
    {
        return json_encode([
            'orderReference' => $confirmation->orderReference,
            'shippedAt' => $confirmation->shippedAt->format(\DATE_ATOM),
            'carrier' => $confirmation->carrier,
            'trackingNumber' => $confirmation->trackingNumber,
            'parcels' => $confirmation->parcels,
            'lines' => array_map(static fn (ShippedLine $line): array => ['sku' => $line->sku, 'units' => $line->units], $confirmation->lines),
        ], \JSON_THROW_ON_ERROR);
    }

    /**
     * @throws \UnexpectedValueException when the text is not a confirmation that was encoded here
     */
    public static function decode(string $json): ShipmentConfirmation
    {
        $data = json_decode($json, true, 8, \JSON_THROW_ON_ERROR);
        if (!\is_array($data)
            || !\is_string($data['orderReference'] ?? null)
            || !\is_string($data['shippedAt'] ?? null)
            || !\is_string($data['carrier'] ?? null)
            || !\is_int($data['parcels'] ?? null)
            || !\is_array($data['lines'] ?? null)) {
            throw new \UnexpectedValueException('The outbox holds a confirmation that is not valid.');
        }

        $tracking = $data['trackingNumber'] ?? null;
        if (null !== $tracking && !\is_string($tracking)) {
            throw new \UnexpectedValueException('The outbox holds a confirmation with a tracking number that is not valid.');
        }

        $lines = [];
        foreach ($data['lines'] as $line) {
            if (!\is_array($line) || !\is_string($line['sku'] ?? null) || !\is_int($line['units'] ?? null)) {
                throw new \UnexpectedValueException('The outbox holds a confirmation with a line that is not valid.');
            }
            $lines[] = new ShippedLine($line['sku'], $line['units']);
        }

        return new ShipmentConfirmation(
            $data['orderReference'],
            new \DateTimeImmutable($data['shippedAt']),
            $data['carrier'],
            $tracking,
            $data['parcels'],
            $lines,
        );
    }
}
