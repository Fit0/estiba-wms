<?php

declare(strict_types=1);

namespace App\Warehouse\Application\AssignZoneFamilies;

use App\Shared\Application\Bus\Command;
use App\Warehouse\Domain\Warehouse;

/**
 * Replaces the product families a zone keeps.
 *
 * @implements Command<Warehouse>
 */
final readonly class AssignZoneFamilies implements Command
{
    /**
     * @param list<string> $families
     */
    public function __construct(
        public string $zoneId,
        public array $families,
    ) {
    }
}
