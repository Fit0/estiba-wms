<?php

declare(strict_types=1);

namespace App\Inbound\Application;

use App\Inbound\Domain\Exception\ReceiptNotFound;
use App\Inbound\Domain\Receipt;
use App\Inbound\Domain\ReceiptId;
use App\Inbound\Domain\ReceiptRepository;
use App\Shared\Application\CurrentTenant;

/**
 * Finds a receipt of the current tenant. A receipt of another tenant is reported as not found.
 */
final readonly class ReceiptLookup
{
    public function __construct(
        private ReceiptRepository $receipts,
        private CurrentTenant $tenant,
    ) {
    }

    /**
     * @throws ReceiptNotFound
     */
    public function get(string $id): Receipt
    {
        $receipt = $this->receipts->find(ReceiptId::fromString($id));

        if (!$receipt instanceof Receipt || !$receipt->tenantId()->equals($this->tenant->id())) {
            throw ReceiptNotFound::forId($id);
        }

        return $receipt;
    }
}
