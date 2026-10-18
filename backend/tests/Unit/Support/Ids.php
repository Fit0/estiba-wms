<?php

declare(strict_types=1);

namespace App\Tests\Unit\Support;

/**
 * Valid UUID v7 values for tests. The domain never generates identifiers, so tests hand them in.
 */
final class Ids
{
    private static int $counter = 0;

    public static function next(): string
    {
        return \sprintf('01929b3e-7c1a-7000-8000-%012d', ++self::$counter);
    }
}
