<?php

declare(strict_types=1);

namespace App\Outbound\Application\GetPickList;

use App\Outbound\Domain\PickList;
use App\Shared\Application\Bus\Query;

/**
 * One pick list of the current tenant.
 *
 * @implements Query<PickList>
 */
final readonly class GetPickList implements Query
{
    public function __construct(public string $pickListId)
    {
    }
}
