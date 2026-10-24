<?php

declare(strict_types=1);

namespace App\Outbound\Domain;

use App\Shared\Domain\Identifier;

/**
 * The user who walks a pick list, seen from Outbound. Only the identifier is held: users live in Identity (ADR 0003).
 */
final readonly class OperatorId extends Identifier
{
}
