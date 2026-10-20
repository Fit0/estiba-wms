<?php

declare(strict_types=1);

namespace App\Shared\Application;

final class TenantNotResolved extends \RuntimeException
{
    public static function missing(): self
    {
        return new self('There is no authenticated user to take the tenant from.');
    }
}
