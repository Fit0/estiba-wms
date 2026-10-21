<?php

declare(strict_types=1);

namespace App\Inbound\Domain;

use App\Shared\Domain\Identifier;

/**
 * The user who did the work at the dock: closed a receipt or confirmed a putaway. Only the
 * identifier is held: users live in Identity (ADR 0003).
 */
final readonly class OperatorId extends Identifier
{
}
