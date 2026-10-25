<?php

declare(strict_types=1);

namespace App\Outbound\Infrastructure\Http\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Outbound\Domain\Shipment;
use App\Outbound\Domain\ShipmentStatus;
use App\Outbound\Infrastructure\Http\Input\CloseParcelInput;
use App\Outbound\Infrastructure\Http\Input\DispatchShipmentInput;
use App\Outbound\Infrastructure\Http\Input\StartPackingInput;
use App\Outbound\Infrastructure\Http\State\CloseParcelProcessor;
use App\Outbound\Infrastructure\Http\State\CompletePackingProcessor;
use App\Outbound\Infrastructure\Http\State\DispatchShipmentProcessor;
use App\Outbound\Infrastructure\Http\State\ShipmentProvider;
use App\Outbound\Infrastructure\Http\State\StartPackingProcessor;

/**
 * The label of a parcel is not an operation of this resource, because it is a PDF or a ZPL text and
 * not JSON: see ParcelLabelController.
 */
#[ApiResource(
    shortName: 'Shipment',
    operations: [
        new GetCollection(uriTemplate: '/shipments', provider: ShipmentProvider::class),
        new Get(uriTemplate: '/shipments/{id}', provider: ShipmentProvider::class),
        new Post(uriTemplate: '/shipments', input: StartPackingInput::class, processor: StartPackingProcessor::class),
        // Closing a parcel assigns its SSCC. The answer is the shipment, whose last parcel is the new one.
        new Post(uriTemplate: '/shipments/{id}/parcels', input: CloseParcelInput::class, read: false, processor: CloseParcelProcessor::class),
        new Post(
            uriTemplate: '/shipments/{id}/complete',
            status: 200,
            input: false,
            read: false,
            deserialize: false,
            processor: CompletePackingProcessor::class,
        ),
        // Dispatching hands the parcels to the carrier: the order is shipped and the stock leaves.
        new Post(uriTemplate: '/shipments/{id}/dispatch', status: 200, input: DispatchShipmentInput::class, read: false, processor: DispatchShipmentProcessor::class),
    ],
)]
final readonly class ShipmentResource
{
    /**
     * @param list<ParcelContentResource> $picked
     * @param list<ParcelResource>        $parcels
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        public string $orderId,
        public string $warehouseId,
        public ShipmentStatus $status,
        public array $picked,
        public int $unitsToPack,
        public array $parcels,
        public string $startedAt,
        public ?string $packedAt,
        public ?string $carrier,
        public ?string $trackingNumber,
        public ?string $dispatchedAt,
    ) {
    }

    public static function fromDomain(Shipment $shipment): self
    {
        return new self(
            $shipment->id()->value,
            $shipment->orderId()->value,
            $shipment->warehouseId()->value,
            $shipment->status(),
            array_map(ParcelContentResource::fromDomain(...), $shipment->picked()),
            $shipment->unitsToPack(),
            array_map(ParcelResource::fromDomain(...), $shipment->parcels()),
            $shipment->startedAt()->format(\DATE_ATOM),
            $shipment->packedAt()?->format(\DATE_ATOM),
            $shipment->carrier(),
            $shipment->trackingNumber(),
            $shipment->dispatchedAt()?->format(\DATE_ATOM),
        );
    }
}
