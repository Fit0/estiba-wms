<?php

declare(strict_types=1);

namespace App\Erp\Application;

use App\Catalog\Application\Contract\ProductCatalog;
use App\Catalog\Application\Contract\ProductSnapshot;
use App\Outbound\Application\Contract\ShipmentLeft;
use App\Outbound\Application\Contract\ShippedUnits;
use App\Shared\Application\Bus\EventHandler;

/**
 * Whenever a shipment leaves, the ERP has to be told, if the order came from it (ADR 0049). The confirmation is
 * written down here, in the words of the ERP's catalogue, and sent later by {@see ErpOutboxSender}, so a dispatch never
 * waits for the ERP nor fails because of it. A second delivery of the event queues nothing.
 */
final readonly class QueueShipmentConfirmation implements EventHandler
{
    public function __construct(
        private ImportedOrders $imported,
        private ProductCatalog $catalog,
        private ShipmentOutbox $outbox,
    ) {
    }

    public function __invoke(ShipmentLeft $event): void
    {
        if (!$this->imported->has($event->tenantId, $event->orderReference)) {
            return;
        }

        $confirmation = new ShipmentConfirmation(
            $event->orderReference,
            $event->occurredAt(),
            $event->carrier,
            $event->trackingNumber,
            $event->parcels,
            array_map(fn (ShippedUnits $line): ShippedLine => new ShippedLine($this->skuOf($event, $line->productId), $line->units), $event->lines),
        );

        $this->outbox->enqueue($event->tenantId, $event->shipmentId, $confirmation, $event->occurredAt());
    }

    private function skuOf(ShipmentLeft $event, string $productId): string
    {
        $product = $this->catalog->findById($event->tenantId, $productId);
        if (!$product instanceof ProductSnapshot) {
            throw new \LogicException(\sprintf('The product %s of a shipment that left must exist.', $productId));
        }

        return $product->sku;
    }
}
