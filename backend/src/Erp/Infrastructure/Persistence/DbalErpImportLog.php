<?php

declare(strict_types=1);

namespace App\Erp\Infrastructure\Persistence;

use App\Erp\Application\ErpImportLog;
use App\Erp\Application\ImportLogEntry;
use App\Erp\Application\ImportResult;
use App\Shared\Domain\Page;
use App\Shared\Domain\PageRequest;
use App\Shared\Domain\TenantId;
use Doctrine\DBAL\Connection;

final readonly class DbalErpImportLog implements ErpImportLog
{
    public function __construct(private Connection $connection)
    {
    }

    #[\Override]
    public function record(TenantId $tenantId, ImportLogEntry $entry): void
    {
        $this->connection->executeStatement(
            'INSERT INTO erp_import_log (id, tenant_id, file_name, result, orders, error_code, message, recorded_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON CONFLICT (id) DO NOTHING',
            [$entry->id, $tenantId->value, $entry->fileName, $entry->result->value, $entry->orders, $entry->errorCode, $entry->message, Utc::text($entry->recordedAt)],
        );
    }

    #[\Override]
    public function search(TenantId $tenantId, ?ImportResult $result, PageRequest $page): Page
    {
        $where = 'tenant_id = ?';
        $parameters = [$tenantId->value];
        if ($result instanceof ImportResult) {
            $where .= ' AND result = ?';
            $parameters[] = $result->value;
        }

        $total = $this->connection->fetchOne('SELECT COUNT(*) FROM erp_import_log WHERE '.$where, $parameters);
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, file_name, result, orders, error_code, message, recorded_at FROM erp_import_log WHERE '.$where
            .' ORDER BY recorded_at DESC, id LIMIT '.$page->itemsPerPage.' OFFSET '.$page->offset(),
            $parameters,
        );

        return new Page(array_map($this->entry(...), $rows), \is_int($total) ? $total : 0, $page);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function entry(array $row): ImportLogEntry
    {
        if (!\is_string($row['id']) || !\is_string($row['file_name']) || !\is_string($row['result']) || !\is_int($row['orders'])
            || (null !== $row['error_code'] && !\is_string($row['error_code'])) || (null !== $row['message'] && !\is_string($row['message']))) {
            throw new \UnexpectedValueException('A row of the import log of the ERP does not have the columns it should.');
        }

        return new ImportLogEntry($row['id'], $row['file_name'], ImportResult::from($row['result']), $row['orders'], $row['error_code'], $row['message'], Utc::parse($row['recorded_at']));
    }
}
