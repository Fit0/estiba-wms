<?php

declare(strict_types=1);

namespace App\Outbound\Infrastructure\Http\Input;

/**
 * The order whose picked units are going to be packed.
 */
final readonly class StartPackingInput
{
    public function __construct(public string $orderId)
    {
    }
}
