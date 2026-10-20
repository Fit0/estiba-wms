<?php

declare(strict_types=1);

namespace App\Tests\Functional\Shared\Messaging\Fixtures;

use App\Shared\Application\Bus\Query;

/**
 * Asks whether a transaction is open while the question is answered.
 *
 * @implements Query<bool>
 */
final readonly class IsInATransaction implements Query
{
}
