<?php

declare(strict_types=1);

namespace App\Outbound\Domain;

/**
 * Hands out the SSCC of each parcel. It never gives the same one twice, even to two packers at
 * once, and it gives one back only if the parcel it was for is not closed (OUT-19).
 */
interface SsccSequence
{
    public function next(): Sscc;
}
