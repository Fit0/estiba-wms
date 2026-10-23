<?php

declare(strict_types=1);

namespace App\Outbound\Domain;

use App\Shared\Domain\Identifier;

/**
 * A group of orders released to the floor together.
 */
final readonly class WaveId extends Identifier
{
}
