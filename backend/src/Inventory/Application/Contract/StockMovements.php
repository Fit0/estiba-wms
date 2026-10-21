<?php

declare(strict_types=1);

namespace App\Inventory\Application\Contract;

use App\Shared\Domain\DomainError;

/**
 * How the other contexts change stock (ADR 0003). Every change is one or two movements of the
 * ledger, written in the caller's transaction (STK-03).
 */
interface StockMovements
{
    /**
     * @throws DomainError when the units or a value are not valid
     */
    public function receive(StockArrival $arrival): void;

    /**
     * Takes the units from one stock item and adds them to another, both or neither (STK-08).
     *
     * @throws DomainError when the source does not hold the units (STK-01)
     */
    public function transfer(StockTransfer $transfer): void;
}
