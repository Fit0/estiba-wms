<?php

declare(strict_types=1);

namespace App\Inbound\Application\CancelAsn;

use App\Inbound\Domain\Asn;
use App\Shared\Application\Bus\Command;

/**
 * @implements Command<Asn>
 */
final readonly class CancelAsn implements Command
{
    public function __construct(public string $asnId)
    {
    }
}
