<?php

declare(strict_types=1);

namespace App\Demo\Fashion;

final readonly class DemoZone
{
    public function __construct(
        public string $code,
        public string $name,
        public int $pickSequence,
    ) {
    }
}
