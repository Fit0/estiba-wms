<?php

declare(strict_types=1);

namespace App\Inbound\Application\RegisterAsn;

use App\Inbound\Domain\Asn;
use App\Shared\Application\Bus\Command;

/**
 * @implements Command<Asn>
 */
final readonly class RegisterAsn implements Command
{
    /**
     * @param list<RegisterAsnLine> $lines
     */
    public function __construct(
        public string $reference,
        public string $supplier,
        public \DateTimeImmutable $expectedDate,
        public array $lines,
    ) {
    }
}
