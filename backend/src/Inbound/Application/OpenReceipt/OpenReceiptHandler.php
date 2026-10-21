<?php

declare(strict_types=1);

namespace App\Inbound\Application\OpenReceipt;

use App\Inbound\Application\AsnLookup;
use App\Inbound\Domain\Exception\InvalidDockLocation;
use App\Inbound\Domain\LocationId;
use App\Inbound\Domain\Receipt;
use App\Inbound\Domain\ReceiptId;
use App\Inbound\Domain\ReceiptRepository;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Clock;
use App\Shared\Application\CurrentTenant;
use App\Shared\Application\IdGenerator;
use App\Warehouse\Application\Contract\LocationDirectory;
use App\Warehouse\Application\Contract\LocationSnapshot;

/**
 * The ASN and the new receipt change in the same transaction: the ASN remembers which receipt is
 * open (INB-16). Both belong to Inbound, so the command bus commits them together.
 */
final readonly class OpenReceiptHandler implements CommandHandler
{
    public function __construct(
        private AsnLookup $lookup,
        private ReceiptRepository $receipts,
        private LocationDirectory $locations,
        private CurrentTenant $tenant,
        private IdGenerator $ids,
        private Clock $clock,
    ) {
    }

    public function __invoke(OpenReceipt $command): Receipt
    {
        $asn = $this->lookup->get($command->asnId);
        $dock = $this->dock($command->dockLocationId);

        $receipt = $asn->openReceipt(ReceiptId::fromString($this->ids->next()), $dock, $this->clock->now());
        $this->receipts->save($receipt);

        return $receipt;
    }

    /**
     * @throws InvalidDockLocation
     */
    private function dock(string $locationId): LocationId
    {
        $location = $this->locations->findById($this->tenant->id(), $locationId);

        if (!$location instanceof LocationSnapshot) {
            throw InvalidDockLocation::unknown($locationId);
        }
        if (!$location->isDock()) {
            throw InvalidDockLocation::notADock($locationId);
        }
        if (!$location->active) {
            throw InvalidDockLocation::blocked($locationId);
        }

        return LocationId::fromString($location->id);
    }
}
