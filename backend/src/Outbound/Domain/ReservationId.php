<?php

declare(strict_types=1);

namespace App\Outbound\Domain;

use App\Shared\Domain\Identifier;

/**
 * The reservation of stock that serves an order line, seen from Outbound. It lives in Inventory (ADR 0003).
 */
final readonly class ReservationId extends Identifier
{
}
