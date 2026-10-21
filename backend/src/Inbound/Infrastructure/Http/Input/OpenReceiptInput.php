<?php

declare(strict_types=1);

namespace App\Inbound\Infrastructure\Http\Input;

final readonly class OpenReceiptInput
{
    public function __construct(public string $dockLocationId)
    {
    }
}
