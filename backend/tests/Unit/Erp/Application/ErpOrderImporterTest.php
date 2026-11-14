<?php

declare(strict_types=1);

namespace App\Tests\Unit\Erp\Application;

use App\Catalog\Application\Contract\ProductCatalog;
use App\Catalog\Application\Contract\ProductSnapshot;
use App\Erp\Application\ErpOrderFiles;
use App\Erp\Application\ErpOrderImporter;
use App\Erp\Application\ErpOrderTranslator;
use App\Erp\Application\ImportLogEntry;
use App\Erp\Application\ImportResult;
use App\Erp\Domain\ErpOrder;
use App\Erp\Domain\ErpOrderLine;
use App\Erp\Domain\ErpUrgency;
use App\Erp\Domain\Exception\ErpUnavailable;
use App\Erp\Domain\Exception\MalformedOrderFile;
use App\Outbound\Application\Contract\IncomingOrder;
use App\Outbound\Application\Contract\IntakeResult;
use App\Outbound\Application\Contract\OrderIntake;
use App\Shared\Application\Clock;
use App\Shared\Application\IdGenerator;
use App\Shared\Domain\StorageClass;
use App\Shared\Domain\TenantId;
use App\Shared\Domain\TurnoverClass;
use App\Tests\Support\InMemoryErpImportLog;
use App\Tests\Support\InMemoryErpOrderInbox;
use App\Tests\Support\InMemoryImportedOrders;
use App\Tests\Unit\Support\AssertsDomainErrors;
use App\Tests\Unit\Support\Ids;
use App\Warehouse\Application\Contract\WarehouseDirectory;
use App\Warehouse\Application\Contract\WarehouseSnapshot;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * The import with the ERP replaced by in-memory fakes: a file whose content is `order:P1` holds the order
 * P1, `order:P1,P2` holds two, `broken` is not a valid file and `order:P3:NOPE` names an unknown article.
 */
final class ErpOrderImporterTest extends TestCase
{
    use AssertsDomainErrors;

    private TenantId $tenant;

    private InMemoryErpOrderInbox $inbox;

    private InMemoryImportedOrders $imported;

    private InMemoryErpImportLog $log;

    /** @var list<string> the references that are registered */
    private array $registered = [];

    #[\Override]
    protected function setUp(): void
    {
        $this->tenant = TenantId::fromString(Ids::next());
        $this->inbox = new InMemoryErpOrderInbox();
        $this->imported = new InMemoryImportedOrders();
        $this->log = new InMemoryErpImportLog();
        $this->registered = [];
    }

    public function testRegistersTheOrdersOfEveryFileAndPutsTheFilesAside(): void
    {
        $this->inbox->waiting = ['a.csv' => 'order:P1', 'b.csv' => 'order:P2,P3'];

        $report = $this->importer()->import($this->tenant, 'ZGZ1');

        self::assertSame(['P1', 'P2', 'P3'], $this->registered);
        self::assertSame(3, $report->registered);
        self::assertSame(0, $report->alreadyRegistered);
        self::assertSame(0, $report->rejectedFiles);
        self::assertSame(['a.csv', 'b.csv'], $this->inbox->processed);
        self::assertSame([], $this->inbox->waiting);
    }

    public function testWritesEveryFileInTheLogWithWhatBecameOfIt(): void
    {
        $this->inbox->waiting = ['a.csv' => 'order:P1,P2', 'b.csv' => 'broken', 'c.csv' => 'order:P3:NOPE'];

        $this->importer()->import($this->tenant, 'ZGZ1');

        self::assertCount(3, $this->log->entries);
        [$processed, $malformed, $unknown] = $this->log->entries;
        self::assertSame(['a.csv', ImportResult::Processed, 2, null], [$processed->fileName, $processed->result, $processed->orders, $processed->errorCode]);
        self::assertSame(['b.csv', ImportResult::Rejected, 0, 'erp.order_file_malformed'], [$malformed->fileName, $malformed->result, $malformed->orders, $malformed->errorCode]);
        self::assertSame(['c.csv', ImportResult::Rejected, 'erp.article_unknown'], [$unknown->fileName, $unknown->result, $unknown->errorCode]);
        self::assertNotNull($unknown->message);
        self::assertSame(['log-1', 'log-2', 'log-3'], array_map(static fn (ImportLogEntry $entry): string => $entry->id, $this->log->entries));
    }

    public function testRemembersTheOrdersItRegistersSoTheirShipmentsAreConfirmedToTheErp(): void
    {
        $this->inbox->waiting = ['a.csv' => 'order:P1,P2', 'b.csv' => 'order:P3:NOPE'];

        $this->importer()->import($this->tenant, 'ZGZ1');

        self::assertTrue($this->imported->has($this->tenant, 'P1'));
        self::assertTrue($this->imported->has($this->tenant, 'P2'));
        self::assertFalse($this->imported->has($this->tenant, 'P3'), 'An order that was rejected is not in the warehouse.');
    }

    public function testAnOrderThatIsAlreadyRegisteredIsCountedAndItsFileIsPutAsideAnyway(): void
    {
        $this->registered = ['P1'];
        $this->inbox->waiting = ['a.csv' => 'order:P1'];

        $report = $this->importer()->import($this->tenant, 'ZGZ1');

        self::assertSame(0, $report->registered);
        self::assertSame(1, $report->alreadyRegistered);
        self::assertSame(['a.csv'], $this->inbox->processed);
    }

    public function testAFileThatIsNotValidIsRejectedAndTheOthersGoOn(): void
    {
        $this->inbox->waiting = ['a.csv' => 'broken', 'b.csv' => 'order:P2'];

        $report = $this->importer()->import($this->tenant, 'ZGZ1');

        self::assertSame(['a.csv'], $this->inbox->rejected);
        self::assertSame(['b.csv'], $this->inbox->processed);
        self::assertSame(['P2'], $this->registered);
        self::assertSame(1, $report->rejectedFiles);
        self::assertSame(1, $report->registered);
    }

    public function testAFileWithAnArticleTheCatalogueDoesNotHaveIsRejected(): void
    {
        $this->inbox->waiting = ['a.csv' => 'order:P3:NOPE'];

        $report = $this->importer()->import($this->tenant, 'ZGZ1');

        self::assertSame(['a.csv'], $this->inbox->rejected);
        self::assertSame([], $this->registered);
        self::assertSame(1, $report->rejectedFiles);
    }

    public function testRunningItAgainAfterTheFilesWerePutAsideDoesNothing(): void
    {
        $this->inbox->waiting = ['a.csv' => 'order:P1'];
        $importer = $this->importer();

        $importer->import($this->tenant, 'ZGZ1');
        $second = $importer->import($this->tenant, 'ZGZ1');

        self::assertSame(['P1'], $this->registered);
        self::assertSame(0, $second->registered + $second->alreadyRegistered + $second->rejectedFiles);
    }

    public function testWhenTheInboxIsDownTheRunStopsAndNothingIsLost(): void
    {
        $this->inbox->waiting = ['a.csv' => 'order:P1'];
        $this->inbox->down = true;

        try {
            $this->importer()->import($this->tenant, 'ZGZ1');
            self::fail('A run cannot go on without the inbox.');
        } catch (ErpUnavailable) {
            self::assertSame([], $this->registered);
            self::assertSame(['a.csv' => 'order:P1'], $this->inbox->waiting);
        }
    }

    public function testAWarehouseThatTheTenantDoesNotHaveStopsTheRunBeforeTouchingAnyFile(): void
    {
        $this->inbox->waiting = ['a.csv' => 'order:P1'];

        self::assertDomainError('erp.warehouse_unknown', ['code' => 'NOPE'], function (): void {
            $this->importer()->import($this->tenant, 'NOPE');
        });
        self::assertSame(['a.csv' => 'order:P1'], $this->inbox->waiting);
    }

    private function importer(): ErpOrderImporter
    {
        $files = new readonly class implements ErpOrderFiles {
            #[\Override]
            public function read(string $contents): array
            {
                if (!str_starts_with($contents, 'order:')) {
                    throw MalformedOrderFile::at(2, 'Fecha');
                }

                $orders = [];
                $parts = explode(':', substr($contents, \strlen('order:')));
                foreach (explode(',', $parts[0]) as $number) {
                    $orders[] = new ErpOrder($number, new \DateTimeImmutable('2026-11-06'), 'Cliente', 'Calle 1', '50001', 'Zaragoza', 'ES', ErpUrgency::Normal, new \DateTimeImmutable('2026-11-08 14:00'), [new ErpOrderLine(1, $parts[1] ?? 'PV27-CAM', 2)]);
                }

                return $orders;
            }
        };

        $catalog = new readonly class implements ProductCatalog {
            #[\Override]
            public function findById(TenantId $tenantId, string $productId): ?ProductSnapshot
            {
                return null;
            }

            #[\Override]
            public function findByGtin(TenantId $tenantId, string $gtin): ?ProductSnapshot
            {
                return null;
            }

            #[\Override]
            public function findBySku(TenantId $tenantId, string $sku): ?ProductSnapshot
            {
                return 'NOPE' === $sku ? null : new ProductSnapshot('0197a3a4-0000-7000-8000-000000000001', $sku, 'Producto', StorageClass::Ambient, false, false, null, null, true, null, TurnoverClass::B, 350, 250, 40, 250);
            }
        };

        $registered = &$this->registered;
        $intake = new readonly class(static function (string $reference) use (&$registered): IntakeResult {
            if (\in_array($reference, $registered, true)) {
                return IntakeResult::AlreadyRegistered;
            }
            $registered[] = $reference;

            return IntakeResult::Registered;
        }) implements OrderIntake {
            /**
             * @param \Closure(string): IntakeResult $register
             */
            public function __construct(private \Closure $register)
            {
            }

            #[\Override]
            public function register(IncomingOrder $order): IntakeResult
            {
                return ($this->register)($order->reference);
            }
        };

        $warehouses = new readonly class implements WarehouseDirectory {
            #[\Override]
            public function findById(TenantId $tenantId, string $warehouseId): ?WarehouseSnapshot
            {
                return null;
            }

            #[\Override]
            public function findByCode(TenantId $tenantId, string $code): ?WarehouseSnapshot
            {
                return 'ZGZ1' === $code ? new WarehouseSnapshot('0197a3a4-0000-7000-8000-0000000000aa', 'ZGZ1', 'Zaragoza') : null;
            }
        };

        return new ErpOrderImporter($this->inbox, $files, new ErpOrderTranslator($catalog), $intake, $warehouses, $this->imported, new readonly class implements Clock {
            #[\Override]
            public function now(): \DateTimeImmutable
            {
                return new \DateTimeImmutable('2026-11-09 10:00:00');
            }
        }, $this->log, new class implements IdGenerator {
            private int $next = 0;

            #[\Override]
            public function next(): string
            {
                return 'log-'.++$this->next;
            }
        }, new NullLogger());
    }
}
