<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Erp\Application\ImportedOrders;
use App\Shared\Domain\TenantId;

final class InMemoryImportedOrders implements ImportedOrders
{
    /** @var list<string> */
    public array $remembered = [];

    #[\Override]
    public function remember(TenantId $tenantId, string $reference, \DateTimeImmutable $at): void
    {
        $this->remembered[] = $tenantId->value.'/'.$reference;
    }

    #[\Override]
    public function has(TenantId $tenantId, string $reference): bool
    {
        return \in_array($tenantId->value.'/'.$reference, $this->remembered, true);
    }
}
