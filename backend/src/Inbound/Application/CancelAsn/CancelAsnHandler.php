<?php

declare(strict_types=1);

namespace App\Inbound\Application\CancelAsn;

use App\Inbound\Application\AsnLookup;
use App\Inbound\Domain\Asn;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Application\Clock;

final readonly class CancelAsnHandler implements CommandHandler
{
    public function __construct(
        private AsnLookup $lookup,
        private EventBus $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(CancelAsn $command): Asn
    {
        $asn = $this->lookup->get($command->asnId);

        $asn->cancel($this->clock->now());

        $this->events->publish(...$asn->pullEvents());

        return $asn;
    }
}
