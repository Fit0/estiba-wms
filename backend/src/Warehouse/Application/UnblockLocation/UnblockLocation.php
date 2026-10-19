<?php

declare(strict_types=1);

namespace App\Warehouse\Application\UnblockLocation;

final readonly class UnblockLocation
{
    public function __construct(public string $locationId)
    {
    }
}
