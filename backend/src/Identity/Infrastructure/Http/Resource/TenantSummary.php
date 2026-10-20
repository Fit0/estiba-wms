<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Http\Resource;

use App\Identity\Domain\Tenant;

/**
 * The tenant of the signed-in user, as the API shows it.
 */
final readonly class TenantSummary
{
    public function __construct(
        public string $id,
        public string $name,
        public string $defaultLanguage,
    ) {
    }

    public static function fromDomain(Tenant $tenant): self
    {
        return new self($tenant->id()->value, $tenant->name()->value, $tenant->defaultLanguage()->value);
    }
}
