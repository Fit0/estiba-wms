<?php

declare(strict_types=1);

namespace App\Erp\Infrastructure\Persistence;

use App\Erp\Application\ImportedOrders;
use App\Shared\Domain\TenantId;
use Doctrine\DBAL\Connection;

final readonly class DbalImportedOrders implements ImportedOrders
{
    public function __construct(private Connection $connection)
    {
    }

    #[\Override]
    public function remember(TenantId $tenantId, string $reference, \DateTimeImmutable $at): void
    {
        $this->connection->executeStatement(
            'INSERT INTO erp_imported_orders (tenant_id, reference, imported_at) VALUES (?, ?, ?) ON CONFLICT DO NOTHING',
            [$tenantId->value, $reference, Utc::text($at)],
        );
    }

    #[\Override]
    public function has(TenantId $tenantId, string $reference): bool
    {
        return false !== $this->connection->fetchOne(
            'SELECT 1 FROM erp_imported_orders WHERE tenant_id = ? AND reference = ?',
            [$tenantId->value, $reference],
        );
    }
}
