<?php

declare(strict_types=1);

namespace App\Warehouse\Domain;

use App\Shared\Domain\AggregateRoot;
use App\Shared\Domain\TenantId;
use App\Warehouse\Domain\Event\LocationBlocked;
use App\Warehouse\Domain\Event\LocationCreated;
use App\Warehouse\Domain\Event\LocationUnblocked;
use App\Warehouse\Domain\Exception\LocationAlreadyBlocked;
use App\Warehouse\Domain\Exception\LocationNotBlocked;

/**
 * The smallest place where stock can be put.
 *
 * A location is never deleted, only blocked (WH-07), so there is no method to remove one.
 */
final class Location extends AggregateRoot
{
    private LocationStatus $status = LocationStatus::Active;

    private ?BlockReason $blockReason = null;

    private function __construct(
        private readonly LocationId $id,
        private readonly TenantId $tenantId,
        private readonly WarehouseId $warehouseId,
        private readonly ZoneId $zoneId,
        private readonly LocationCode $code,
        private readonly LocationType $type,
        private readonly LocationCapacity $capacity,
        private readonly PickSequence $pickSequence,
    ) {
    }

    /**
     * @internal use {@see Warehouse::defineLocation()}, which checks that the zone belongs
     *           to the warehouse (WH-05) and that the code is free (WH-03)
     */
    public static function define(
        LocationId $id,
        TenantId $tenantId,
        WarehouseId $warehouseId,
        ZoneId $zoneId,
        LocationCode $code,
        LocationType $type,
        LocationCapacity $capacity,
        PickSequence $pickSequence,
        \DateTimeImmutable $at,
    ): self {
        $location = new self($id, $tenantId, $warehouseId, $zoneId, $code, $type, $capacity, $pickSequence);
        $location->record(new LocationCreated($id, $warehouseId, $zoneId, $code, $type, $at));

        return $location;
    }

    /**
     * @throws LocationAlreadyBlocked
     */
    public function block(BlockReason $reason, \DateTimeImmutable $at): void
    {
        if (LocationStatus::Blocked === $this->status) {
            throw LocationAlreadyBlocked::forCode($this->code);
        }

        $this->status = LocationStatus::Blocked;
        $this->blockReason = $reason;
        $this->record(new LocationBlocked($this->id, $this->warehouseId, $this->code, $reason, $at));
    }

    /**
     * @throws LocationNotBlocked
     */
    public function unblock(\DateTimeImmutable $at): void
    {
        if (LocationStatus::Blocked !== $this->status) {
            throw LocationNotBlocked::forCode($this->code);
        }

        $this->status = LocationStatus::Active;
        $this->blockReason = null;
        $this->record(new LocationUnblocked($this->id, $this->warehouseId, $this->code, $at));
    }

    /**
     * Whether putaway and allocation may use this location (WH-06).
     */
    public function isEligibleForStorage(): bool
    {
        return LocationStatus::Active === $this->status;
    }

    public function id(): LocationId
    {
        return $this->id;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function warehouseId(): WarehouseId
    {
        return $this->warehouseId;
    }

    public function zoneId(): ZoneId
    {
        return $this->zoneId;
    }

    public function code(): LocationCode
    {
        return $this->code;
    }

    public function type(): LocationType
    {
        return $this->type;
    }

    public function capacity(): LocationCapacity
    {
        return $this->capacity;
    }

    public function pickSequence(): PickSequence
    {
        return $this->pickSequence;
    }

    public function status(): LocationStatus
    {
        return $this->status;
    }

    public function blockReason(): ?BlockReason
    {
        return $this->blockReason;
    }
}
