<?php

declare(strict_types=1);

namespace App\Inbound\Application\OpenReceipt;

use App\Inbound\Domain\Receipt;
use App\Shared\Application\Bus\Command;

/**
 * @implements Command<Receipt>
 */
final readonly class OpenReceipt implements Command
{
    public function __construct(
        public string $asnId,
        public string $dockLocationId,
    ) {
    }
}
