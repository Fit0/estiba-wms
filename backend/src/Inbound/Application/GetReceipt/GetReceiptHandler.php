<?php

declare(strict_types=1);

namespace App\Inbound\Application\GetReceipt;

use App\Inbound\Application\ReceiptLookup;
use App\Inbound\Domain\Exception\ReceiptNotFound;
use App\Inbound\Domain\Receipt;
use App\Shared\Application\Bus\QueryHandler;

final readonly class GetReceiptHandler implements QueryHandler
{
    public function __construct(private ReceiptLookup $lookup)
    {
    }

    /**
     * @throws ReceiptNotFound
     */
    public function __invoke(GetReceipt $query): Receipt
    {
        return $this->lookup->get($query->receiptId);
    }
}
