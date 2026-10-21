<?php

declare(strict_types=1);

namespace App\Inbound\Application\CloseReceipt;

use App\Inbound\Domain\Receipt;
use App\Shared\Application\Bus\Command;

/**
 * @implements Command<Receipt>
 */
final readonly class CloseReceipt implements Command
{
    public function __construct(public string $receiptId)
    {
    }
}
