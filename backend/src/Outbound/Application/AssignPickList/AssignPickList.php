<?php

declare(strict_types=1);

namespace App\Outbound\Application\AssignPickList;

use App\Outbound\Domain\PickList;
use App\Shared\Application\Bus\Command;

/**
 * A supervisor says which operator walks a pick list, until the operator starts it (OUT-12).
 *
 * @implements Command<PickList>
 */
final readonly class AssignPickList implements Command
{
    public function __construct(
        public string $pickListId,
        public string $operatorId,
    ) {
    }
}
