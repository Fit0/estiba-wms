<?php

declare(strict_types=1);

namespace App\Outbound\Domain;

/**
 * One stop of a walk: go to a location and take units of a product, for one line of one order. The
 * stop remembers where it is on the route, so a list can be shown and walked in order (OUT-09).
 *
 * The stop is confirmed once. What was picked never exceeds what was asked, and taking less is a short
 * pick that carries its reason (OUT-10). The stock item says which units the stop is about, so the
 * pick moves exactly those.
 */
final readonly class PickLine
{
    public function __construct(
        public int $position,
        public OrderId $orderId,
        public int $orderLineNumber,
        public ProductId $productId,
        public string $stockItemId,
        public string $locationId,
        public string $locationCode,
        public ?string $lot,
        public ?\DateTimeImmutable $expiryDate,
        public int $quantityToPick,
        public int $quantityPicked = 0,
        public bool $confirmed = false,
        public ?ShortPickReason $shortReason = null,
    ) {
    }

    public static function at(int $position, PickRequest $request): self
    {
        return new self(
            $position,
            $request->orderId,
            $request->orderLineNumber,
            $request->productId,
            $request->stockItemId,
            $request->locationId,
            $request->locationCode,
            $request->lot,
            $request->expiryDate,
            $request->units,
        );
    }

    public function confirmedWith(int $picked, ?ShortPickReason $reason): self
    {
        return new self(
            $this->position,
            $this->orderId,
            $this->orderLineNumber,
            $this->productId,
            $this->stockItemId,
            $this->locationId,
            $this->locationCode,
            $this->lot,
            $this->expiryDate,
            $this->quantityToPick,
            $picked,
            true,
            $picked < $this->quantityToPick ? $reason : null,
        );
    }

    /**
     * The units the operator did not find. Nothing is missing until the stop is confirmed.
     */
    public function missingUnits(): int
    {
        return $this->confirmed ? $this->quantityToPick - $this->quantityPicked : 0;
    }
}
