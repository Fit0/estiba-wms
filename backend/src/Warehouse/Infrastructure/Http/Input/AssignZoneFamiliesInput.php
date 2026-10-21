<?php

declare(strict_types=1);

namespace App\Warehouse\Infrastructure\Http\Input;

/**
 * The families the zone keeps from now on, such as `["CAMISERIA", "PUNTO"]`. An empty list keeps none.
 */
final readonly class AssignZoneFamiliesInput
{
    /**
     * @param array<array-key, mixed> $families
     */
    public function __construct(public array $families = [])
    {
    }
}
