<?php

declare(strict_types=1);

namespace App\Outbound\Application\Contract;

use App\Shared\Domain\DomainError;

/**
 * How another context hands an order to Outbound (ADR 0003). The order is registered for the current
 * tenant, with the same rules as one registered through the API.
 */
interface OrderIntake
{
    /**
     * @throws DomainError when the order breaks a rule: an unknown warehouse or product, an empty line list, a bad address
     */
    public function register(IncomingOrder $order): IntakeResult;
}
