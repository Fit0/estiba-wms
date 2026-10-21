<?php

declare(strict_types=1);

namespace App\Inbound\Application\CloseAsn;

use App\Inbound\Domain\Asn;
use App\Shared\Application\Bus\Command;

/**
 * @implements Command<Asn>
 */
final readonly class CloseAsn implements Command
{
    public function __construct(public string $asnId)
    {
    }
}
