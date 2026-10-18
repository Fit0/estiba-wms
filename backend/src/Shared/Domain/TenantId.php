<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * The company whose goods and data the system holds. Every aggregate belongs to one.
 */
final readonly class TenantId extends Identifier
{
}
