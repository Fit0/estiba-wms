<?php

declare(strict_types=1);

namespace App\Erp\Application;

use App\Erp\Domain\Exception\ErpUnavailable;
use App\Erp\Domain\Exception\WarehouseNotConfigured;
use App\Outbound\Application\Contract\IntakeResult;
use App\Outbound\Application\Contract\OrderIntake;
use App\Shared\Application\Clock;
use App\Shared\Application\IdGenerator;
use App\Shared\Domain\DomainError;
use App\Shared\Domain\TenantId;
use App\Warehouse\Application\Contract\WarehouseDirectory;
use Psr\Log\LoggerInterface;

/**
 * Takes the order files the ERP left and registers their orders (ADR 0048). Running it twice is safe:
 * a file is put aside once it is dealt with, and an order that is already registered is not registered again
 * (OUT-01), so a run that stopped halfway just finishes the next time.
 *
 * A file that cannot become an order, because it is malformed or names an unknown article, goes to the
 * rejected files and the rest go on: one bad file must not hold back the others. A failure of the ERP's
 * side, or of our own, stops the run and leaves the files where they are for the next one. Every file that is
 * dealt with is written in the import log, with what became of it (ADR 0050).
 */
final readonly class ErpOrderImporter
{
    public function __construct(
        private ErpOrderInbox $inbox,
        private ErpOrderFiles $files,
        private ErpOrderTranslator $translator,
        private OrderIntake $intake,
        private WarehouseDirectory $warehouses,
        private ImportedOrders $imported,
        private Clock $clock,
        private ErpImportLog $log,
        private IdGenerator $ids,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @throws WarehouseNotConfigured
     * @throws ErpUnavailable
     */
    public function import(TenantId $tenantId, string $warehouseCode): ImportReport
    {
        $warehouse = $this->warehouses->findByCode($tenantId, $warehouseCode)
            ?? throw WarehouseNotConfigured::forCode($warehouseCode);

        $registered = 0;
        $already = 0;
        $rejected = 0;

        foreach ($this->inbox->pending() as $name) {
            try {
                $results = $this->register($tenantId, $warehouse->id, $this->inbox->read($name));
            } catch (ErpUnavailable $unavailable) {
                throw $unavailable;
            } catch (DomainError $error) {
                $this->logger->warning('An order file of the ERP was rejected.', ['file' => $name, 'code' => $error->errorCode(), 'parameters' => $error->errorParameters()]);
                $this->log->record($tenantId, new ImportLogEntry($this->ids->next(), $name, ImportResult::Rejected, 0, $error->errorCode(), $error->getMessage(), $this->clock->now()));
                $this->inbox->markRejected($name);
                ++$rejected;

                continue;
            }

            $this->log->record($tenantId, new ImportLogEntry($this->ids->next(), $name, ImportResult::Processed, \count($results), null, null, $this->clock->now()));
            $this->inbox->markProcessed($name);
            foreach ($results as $result) {
                IntakeResult::Registered === $result ? ++$registered : ++$already;
            }
        }

        return new ImportReport($registered, $already, $rejected);
    }

    /**
     * @return list<IntakeResult>
     */
    private function register(TenantId $tenantId, string $warehouseId, string $contents): array
    {
        $results = [];
        foreach ($this->files->read($contents) as $order) {
            $results[] = $this->intake->register($this->translator->translate($tenantId, $warehouseId, $order));
            // The order is in the warehouse now, so its shipment will have to be confirmed to the ERP (ADR 0049).
            $this->imported->remember($tenantId, $order->number, $this->clock->now());
        }

        return $results;
    }
}
