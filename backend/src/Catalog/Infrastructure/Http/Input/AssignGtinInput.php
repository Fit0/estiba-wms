<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Http\Input;

final readonly class AssignGtinInput
{
    public function __construct(public string $gtin)
    {
    }
}
