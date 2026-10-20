<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * A language the product speaks (ADR 0004). Spanish is the default.
 */
enum Language: string
{
    public const self DEFAULT = self::Spanish;

    case Spanish = 'es';
    case English = 'en';
}
