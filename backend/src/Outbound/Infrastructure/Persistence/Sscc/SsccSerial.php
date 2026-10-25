<?php

declare(strict_types=1);

namespace App\Outbound\Infrastructure\Persistence\Sscc;

/**
 * The last serial given out for a GS1 company prefix. {@see DbalSsccSequence} moves it with one
 * atomic statement, so two parcels closed at once never get the same SSCC (OUT-19). It is a
 * persistence detail, so it lives here and not in the domain.
 */
final readonly class SsccSerial
{
    public function __construct(
        public string $companyPrefix,
        public int $lastSerial,
    ) {
    }
}
