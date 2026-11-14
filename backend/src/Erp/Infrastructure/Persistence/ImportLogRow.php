<?php

declare(strict_types=1);

namespace App\Erp\Infrastructure\Persistence;

/**
 * A row of the log of imported files. Like {@see OutboxRow}, it describes the table for the schema and
 * {@see DbalErpImportLog} reads and writes it with SQL.
 */
final readonly class ImportLogRow
{
    public function __construct(
        public string $id,
        public string $tenantId,
        public string $fileName,
        public string $result,
        public int $orders,
        public ?string $errorCode,
        public ?string $message,
        public \DateTimeImmutable $recordedAt,
    ) {
    }
}
