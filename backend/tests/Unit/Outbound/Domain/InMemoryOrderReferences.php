<?php

declare(strict_types=1);

namespace App\Tests\Unit\Outbound\Domain;

use App\Outbound\Domain\OrderReference;
use App\Outbound\Domain\OrderReferenceRegistry;
use App\Shared\Domain\TenantId;

final class InMemoryOrderReferences implements OrderReferenceRegistry
{
    /** @var array<string, true> */
    private array $taken = [];

    public function take(TenantId $tenantId, string $reference): self
    {
        $this->taken[$tenantId->value.'|'.$reference] = true;

        return $this;
    }

    #[\Override]
    public function isReferenceTaken(TenantId $tenantId, OrderReference $reference): bool
    {
        return isset($this->taken[$tenantId->value.'|'.$reference->value]);
    }
}
