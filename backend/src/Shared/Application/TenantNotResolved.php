<?php

declare(strict_types=1);

namespace App\Shared\Application;

final class TenantNotResolved extends \RuntimeException
{
    public static function missing(): self
    {
        return new self('The request does not say which tenant it acts for.');
    }
}
