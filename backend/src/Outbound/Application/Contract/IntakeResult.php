<?php

declare(strict_types=1);

namespace App\Outbound\Application\Contract;

/**
 * What happened to an order that came from outside. Sending the same order twice is not an error: the
 * second time nothing changes and the answer says so (OUT-01).
 */
enum IntakeResult
{
    case Registered;
    case AlreadyRegistered;
}
