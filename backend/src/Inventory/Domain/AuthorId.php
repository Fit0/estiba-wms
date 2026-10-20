<?php

declare(strict_types=1);

namespace App\Inventory\Domain;

use App\Shared\Domain\Identifier;

/**
 * The user who caused a movement. Only the identifier is held: users live in Identity (ADR 0003).
 */
final readonly class AuthorId extends Identifier
{
}
