<?php

declare(strict_types=1);

namespace App\Inbound\Domain;

/**
 * There is no delete method: a receipt is a record of what arrived.
 */
interface ReceiptRepository
{
    public function find(ReceiptId $id): ?Receipt;

    public function save(Receipt $receipt): void;
}
