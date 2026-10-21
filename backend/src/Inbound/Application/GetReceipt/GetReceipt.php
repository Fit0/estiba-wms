<?php

declare(strict_types=1);

namespace App\Inbound\Application\GetReceipt;

use App\Inbound\Domain\Receipt;
use App\Shared\Application\Bus\Query;

/**
 * One receipt of the current tenant, with its lines and discrepancies.
 *
 * @implements Query<Receipt>
 */
final readonly class GetReceipt implements Query
{
    public function __construct(public string $receiptId)
    {
    }
}
