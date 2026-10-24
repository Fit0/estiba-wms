<?php

declare(strict_types=1);

namespace App\Outbound\Infrastructure\Http\Input;

final readonly class AssignPickListInput
{
    public function __construct(public string $operatorId)
    {
    }
}
