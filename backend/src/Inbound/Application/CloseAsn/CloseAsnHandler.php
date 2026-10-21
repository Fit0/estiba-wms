<?php

declare(strict_types=1);

namespace App\Inbound\Application\CloseAsn;

use App\Inbound\Application\AsnLookup;
use App\Inbound\Domain\Asn;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Clock;

final readonly class CloseAsnHandler implements CommandHandler
{
    public function __construct(
        private AsnLookup $lookup,
        private Clock $clock,
    ) {
    }

    public function __invoke(CloseAsn $command): Asn
    {
        $asn = $this->lookup->get($command->asnId);

        $asn->close($this->clock->now());

        return $asn;
    }
}
