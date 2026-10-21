<?php

declare(strict_types=1);

namespace App\Inbound\Application\ApproveOverReceipt;

use App\Inbound\Domain\Receipt;
use App\Shared\Application\Bus\Command;

/**
 * A supervisor accepts that the receipt holds more than the ASN announced (INB-08).
 *
 * @implements Command<Receipt>
 */
final readonly class ApproveOverReceipt implements Command
{
    public function __construct(public string $receiptId)
    {
    }
}
