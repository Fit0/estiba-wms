<?php

declare(strict_types=1);

namespace App\Outbound\Infrastructure\Http\Input;

/**
 * The order identifiers arrive as a plain array: without a PHPDoc parser the serializer cannot tell
 * what a list holds, so the processor reads them with {@see CreateWaveOrderIds} and refuses
 * anything that is not a text.
 */
final readonly class CreateWaveInput
{
    /**
     * @param array<array-key, mixed> $orderIds the identifiers of the orders to group
     */
    public function __construct(public array $orderIds = [])
    {
    }
}
