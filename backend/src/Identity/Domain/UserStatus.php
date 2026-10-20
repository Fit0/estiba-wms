<?php

declare(strict_types=1);

namespace App\Identity\Domain;

enum UserStatus: string
{
    case Active = 'active';

    /** Cannot sign in, and any token it already holds stops working (IDN-06). */
    case Disabled = 'disabled';
}
