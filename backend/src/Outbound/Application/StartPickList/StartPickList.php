<?php

declare(strict_types=1);

namespace App\Outbound\Application\StartPickList;

use App\Outbound\Domain\PickList;
use App\Shared\Application\Bus\Command;

/**
 * The signed-in operator starts a pick list. One that nobody was assigned becomes theirs (OUT-12).
 *
 * @implements Command<PickList>
 */
final readonly class StartPickList implements Command
{
    public function __construct(public string $pickListId)
    {
    }
}
