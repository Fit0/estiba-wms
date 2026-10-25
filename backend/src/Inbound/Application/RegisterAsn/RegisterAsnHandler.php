<?php

declare(strict_types=1);

namespace App\Inbound\Application\RegisterAsn;

use App\Inbound\Application\ReceivableProducts;
use App\Inbound\Domain\Asn;
use App\Inbound\Domain\AsnId;
use App\Inbound\Domain\AsnReference;
use App\Inbound\Domain\AsnRepository;
use App\Inbound\Domain\ExpectedLine;
use App\Inbound\Domain\LotNumber;
use App\Inbound\Domain\SupplierName;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Application\Clock;
use App\Shared\Application\CurrentTenant;
use App\Shared\Application\IdGenerator;

final readonly class RegisterAsnHandler implements CommandHandler
{
    public function __construct(
        private AsnRepository $asns,
        private ReceivableProducts $products,
        private CurrentTenant $tenant,
        private IdGenerator $ids,
        private EventBus $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(RegisterAsn $command): Asn
    {
        $asn = Asn::register(
            AsnId::fromString($this->ids->next()),
            $this->tenant->id(),
            AsnReference::fromString($command->reference),
            SupplierName::fromString($command->supplier),
            $command->expectedDate,
            array_map($this->expectedLine(...), $command->lines),
            $this->asns,
            $this->clock->now(),
        );

        $this->asns->save($asn);

        $this->events->publish(...$asn->pullEvents());

        return $asn;
    }

    private function expectedLine(RegisterAsnLine $line): ExpectedLine
    {
        return new ExpectedLine(
            $this->products->byId($line->productId),
            $line->units,
            null === $line->lot ? null : LotNumber::fromString($line->lot),
            $line->expiryDate,
        );
    }
}
