<?php

declare(strict_types=1);

namespace App\Warehouse\Infrastructure\Http\Input;

final readonly class BlockLocationInput
{
    public function __construct(public string $reason)
    {
    }
}
