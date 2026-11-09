<?php

declare(strict_types=1);

namespace App\Erp\Application;

use App\Shared\Application\Bus\JobHandler;
use App\Shared\Application\TenantRunner;
use App\Shared\Domain\TenantId;
use Psr\Log\LoggerInterface;

/**
 * Runs the import for the tenant and the warehouse that the configuration names (ADR 0048). Without a
 * tenant there is nothing to do: the ERP is not set up.
 */
final readonly class ImportErpOrdersHandler implements JobHandler
{
    public function __construct(
        private ErpOrderImporter $importer,
        private TenantRunner $tenants,
        private LoggerInterface $logger,
        private string $tenantId,
        private string $warehouseCode,
    ) {
    }

    /**
     * @return ImportReport|null what the run did, or nothing when the import is not set up
     */
    public function __invoke(ImportErpOrders $message): ?ImportReport
    {
        if ('' === $this->tenantId || '' === $this->warehouseCode) {
            $this->logger->warning('The import of ERP orders is not set up: ERP_TENANT_ID and ERP_WAREHOUSE_CODE are empty.');

            return null;
        }

        $tenantId = TenantId::fromString($this->tenantId);
        $report = $this->tenants->run($tenantId, fn (): ImportReport => $this->importer->import($tenantId, $this->warehouseCode));

        if ($report->registered > 0 || $report->rejectedFiles > 0) {
            $this->logger->info('ERP orders imported.', ['registered' => $report->registered, 'alreadyRegistered' => $report->alreadyRegistered, 'rejectedFiles' => $report->rejectedFiles]);
        }

        return $report;
    }
}
