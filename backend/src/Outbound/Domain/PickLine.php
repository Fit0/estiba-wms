<?php

declare(strict_types=1);

namespace App\Outbound\Domain;

/**
 * One stop of a walk: go to a location and take units of a product, for one line of one order. The
 * stop remembers where it is on the route, so a list can be shown and walked in order (OUT-09).
 *
 * What was picked arrives with picking (OUT-10): it starts at zero and never exceeds what was asked.
 */
final readonly class PickLine
{
    public function __construct(
        public int $position,
        public OrderId $orderId,
        public int $orderLineNumber,
        public ProductId $productId,
        public string $locationId,
        public string $locationCode,
        public ?string $lot,
        public ?\DateTimeImmutable $expiryDate,
        public int $quantityToPick,
        public int $quantityPicked = 0,
    ) {
    }

    public static function at(int $position, PickRequest $request): self
    {
        return new self(
            $position,
            $request->orderId,
            $request->orderLineNumber,
            $request->productId,
            $request->locationId,
            $request->locationCode,
            $request->lot,
            $request->expiryDate,
            $request->units,
        );
    }
}
