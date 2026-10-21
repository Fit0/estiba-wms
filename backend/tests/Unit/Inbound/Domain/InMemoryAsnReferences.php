<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inbound\Domain;

use App\Inbound\Domain\AsnReference;
use App\Inbound\Domain\AsnReferenceRegistry;
use App\Shared\Domain\TenantId;

/**
 * Stands in for the database in unit tests: it remembers which ASN references are taken.
 */
final class InMemoryAsnReferences implements AsnReferenceRegistry
{
    /** @var array<string, true> */
    private array $taken = [];

    public function take(TenantId $tenantId, string $reference): self
    {
        $this->taken[$tenantId->value.'|'.$reference] = true;

        return $this;
    }

    #[\Override]
    public function isReferenceTaken(TenantId $tenantId, AsnReference $reference): bool
    {
        return isset($this->taken[$tenantId->value.'|'.$reference->value]);
    }
}
