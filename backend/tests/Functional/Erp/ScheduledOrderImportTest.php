<?php

declare(strict_types=1);

namespace App\Tests\Functional\Erp;

use App\Catalog\Application\Contract\ProductCatalog;
use App\Erp\Application\ErpOrderImporter;
use App\Erp\Application\ErpOrderTranslator;
use App\Erp\Application\ImportReport;
use App\Erp\Infrastructure\File\CsvErpOrderFiles;
use App\Outbound\Application\Contract\OrderIntake;
use App\Shared\Application\CurrentTenant;
use App\Shared\Domain\TenantId;
use App\Tests\Functional\Outbound\Api\OutboundApiTestCase;
use App\Tests\Support\InMemoryErpOrderInbox;
use App\Tests\Unit\Support\Ids;
use App\Warehouse\Application\Contract\WarehouseDirectory;
use App\Warehouse\Application\Contract\WarehouseSnapshot;
use Psr\Log\NullLogger;

/**
 * The import that the schedule starts (ADR 0048), with the folder of the ERP replaced by an in-memory one and
 * everything else real: nobody is signed in, the tenant comes from the job, and the orders end up in the database.
 */
final class ScheduledOrderImportTest extends OutboundApiTestCase
{
    private const string FILE = 'P2026000004.csv';

    private InMemoryErpOrderInbox $inbox;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['PV27-CAM-LIN-AZU-M', 'PV27-PAN-CHI-ARE-42', 'PV27-PAN-CHI-ARE-40', 'PV27-VES-MID-NEG-S'] as $sku) {
            $this->product($sku);
        }

        $this->inbox = new InMemoryErpOrderInbox();
        $this->inbox->waiting[self::FILE] = $this->fixture();
    }

    public function testTheOrderOfTheFileIsRegisteredForTheTenantOfTheJobAndTheFileIsPutAside(): void
    {
        $report = $this->runImport();

        self::assertSame(1, $report->registered);
        self::assertSame([self::FILE], $this->inbox->processed);
        $orders = $this->request('GET', '/api/orders');
        self::assertCount(1, $orders);
        self::assertIsArray($orders[0]);
        self::assertSame('P2026000004', $orders[0]['reference']);
    }

    public function testTheNextRunFindsNothingAndAFileLeftBehindDoesNotRegisterTheOrderTwice(): void
    {
        $this->runImport();
        $next = $this->runImport();
        self::assertSame(0, $next->registered + $next->alreadyRegistered);

        // The run stopped after registering and before moving the file: the file comes back.
        $this->inbox->waiting[self::FILE] = $this->fixture();
        $again = $this->runImport();

        self::assertSame(1, $again->alreadyRegistered);
        self::assertSame(0, $again->registered);
        self::assertCount(1, $this->request('GET', '/api/orders'));
    }

    public function testAFileWithAnArticleTheCatalogueDoesNotHaveIsRejectedAndRegistersNothing(): void
    {
        $this->inbox->waiting[self::FILE] = str_replace('PV27-CAM-LIN-AZU-M', 'PV27-NO-EXISTE', $this->fixture());

        $report = $this->runImport();

        self::assertSame(1, $report->rejectedFiles);
        self::assertSame([self::FILE], $this->inbox->rejected);
        self::assertSame([], $this->request('GET', '/api/orders'));
    }

    public function testAWarehouseIsFoundByItsCodeInCapitalsOrNot(): void
    {
        $directory = self::getContainer()->get(WarehouseDirectory::class);
        $tenant = TenantId::fromString($this->tenant);

        self::assertSame($this->warehouseId, $directory->findByCode($tenant, 'ZGZ1')?->id);
        self::assertSame($this->warehouseId, $directory->findByCode($tenant, ' zgz1 ')?->id);
        self::assertSame($this->otherWarehouseId, $directory->findByCode($tenant, 'MAD1')?->id);
        self::assertNotInstanceOf(WarehouseSnapshot::class, $directory->findByCode($tenant, 'BCN1'));
    }

    public function testAWarehouseOfAnotherTenantIsNotFound(): void
    {
        $directory = self::getContainer()->get(WarehouseDirectory::class);

        self::assertNotInstanceOf(WarehouseSnapshot::class, $directory->findByCode(TenantId::fromString(Ids::next()), 'ZGZ1'));
    }

    private function runImport(): ImportReport
    {
        $container = self::getContainer();
        // The service that answers CurrentTenant is also the TenantRunner (config/services.yaml).
        $tenants = $container->get(CurrentTenant::class);

        $importer = new ErpOrderImporter(
            $this->inbox,
            new CsvErpOrderFiles(),
            new ErpOrderTranslator($container->get(ProductCatalog::class)),
            $container->get(OrderIntake::class),
            $container->get(WarehouseDirectory::class),
            new NullLogger(),
        );
        $tenant = TenantId::fromString($this->tenant);

        return $tenants->run($tenant, static fn (): ImportReport => $importer->import($tenant, 'ZGZ1'));
    }

    private function fixture(): string
    {
        $file = file_get_contents(__DIR__.'/../../Support/erp/'.self::FILE);
        self::assertIsString($file);

        return $file;
    }
}
