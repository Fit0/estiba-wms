<?php

declare(strict_types=1);

namespace App\Erp\Infrastructure\Persistence;

/**
 * An order that came from the ERP. Like {@see OutboxRow}, it describes the table for the schema and
 * {@see DbalImportedOrders} uses it with SQL.
 */
final readonly class ImportedOrderRow
{
    public function __construct(
        public string $tenantId,
        public string $reference,
        public \DateTimeImmutable $importedAt,
    ) {
    }
}
