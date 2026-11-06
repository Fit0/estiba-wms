<?php

declare(strict_types=1);

namespace App\Tests\Unit\Erp\Application;

use App\Catalog\Application\Contract\ProductCatalog;
use App\Catalog\Application\Contract\ProductSnapshot;
use App\Catalog\Domain\Exception\InvalidSku;
use App\Erp\Application\ErpOrderTranslator;
use App\Erp\Domain\ErpOrder;
use App\Erp\Domain\ErpOrderLine;
use App\Erp\Domain\ErpUrgency;
use App\Outbound\Application\Contract\IncomingOrderPriority;
use App\Shared\Domain\StorageClass;
use App\Shared\Domain\TenantId;
use App\Shared\Domain\TurnoverClass;
use App\Tests\Unit\Support\AssertsDomainErrors;
use App\Tests\Unit\Support\Ids;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ErpOrderTranslatorTest extends TestCase
{
    use AssertsDomainErrors;

    private TenantId $tenant;

    private string $camisaId;

    private string $pantalonId;

    #[\Override]
    protected function setUp(): void
    {
        $this->tenant = TenantId::fromString(Ids::next());
        $this->camisaId = Ids::next();
        $this->pantalonId = Ids::next();
    }

    public function testTurnsAnErpOrderIntoAnOrderOfTheWarehouse(): void
    {
        $due = new \DateTimeImmutable('2026-11-08 14:00', new \DateTimeZone('Europe/Madrid'));
        $warehouseId = Ids::next();

        $incoming = $this->translator()->translate($this->tenant, $warehouseId, $this->order(ErpUrgency::Urgent, $due));

        self::assertSame('P2026000001', $incoming->reference);
        self::assertSame($warehouseId, $incoming->warehouseId);
        self::assertSame('Franquicia Costa del Sol', $incoming->customer);
        self::assertSame('Avenida de Andalucía 118', $incoming->street);
        self::assertSame('29006', $incoming->postalCode);
        self::assertSame('Málaga', $incoming->city);
        self::assertSame('ES', $incoming->countryCode);
        self::assertSame(IncomingOrderPriority::Urgent, $incoming->priority);
        self::assertSame($due, $incoming->cutOffAt);
        self::assertCount(2, $incoming->lines);
        self::assertSame($this->camisaId, $incoming->lines[0]->productId);
        self::assertSame(4, $incoming->lines[0]->units);
        self::assertSame($this->pantalonId, $incoming->lines[1]->productId);
        self::assertSame(1, $incoming->lines[1]->units);
    }

    /**
     * @return iterable<string, array{ErpUrgency, IncomingOrderPriority}>
     */
    public static function urgencies(): iterable
    {
        yield 'B is low' => [ErpUrgency::Low, IncomingOrderPriority::Low];
        yield 'N is normal' => [ErpUrgency::Normal, IncomingOrderPriority::Normal];
        yield 'A is high' => [ErpUrgency::High, IncomingOrderPriority::High];
        yield 'U is urgent' => [ErpUrgency::Urgent, IncomingOrderPriority::Urgent];
    }

    #[DataProvider('urgencies')]
    public function testTranslatesTheLetterOfUrgency(ErpUrgency $urgency, IncomingOrderPriority $priority): void
    {
        $incoming = $this->translator()->translate($this->tenant, Ids::next(), $this->order($urgency));

        self::assertSame($priority, $incoming->priority);
    }

    public function testAnArticleOfTheCatalogueOfAnotherTenantIsUnknown(): void
    {
        $other = TenantId::fromString(Ids::next());

        self::assertDomainError('erp.article_unknown', ['sku' => 'PV27-CAM-LIN-AZU-S'], function () use ($other): void {
            $this->translator()->translate($other, Ids::next(), $this->order(ErpUrgency::Normal));
        });
    }

    public function testAnArticleThatIsNotInTheCatalogueIsNamed(): void
    {
        $order = $this->order(ErpUrgency::Normal, lines: [new ErpOrderLine(1, 'PV27-NOPE', 1)]);

        self::assertDomainError('erp.article_unknown', ['sku' => 'PV27-NOPE'], function () use ($order): void {
            $this->translator()->translate($this->tenant, Ids::next(), $order);
        });
    }

    public function testACodeThatCannotBeASkuIsAnUnknownArticleToo(): void
    {
        $order = $this->order(ErpUrgency::Normal, lines: [new ErpOrderLine(1, '¿?', 1)]);

        self::assertDomainError('erp.article_unknown', ['sku' => '¿?'], function () use ($order): void {
            $this->translator()->translate($this->tenant, Ids::next(), $order);
        });
    }

    /**
     * @param list<ErpOrderLine>|null $lines
     */
    private function order(ErpUrgency $urgency, ?\DateTimeImmutable $due = null, ?array $lines = null): ErpOrder
    {
        return new ErpOrder(
            'P2026000001',
            new \DateTimeImmutable('2026-11-06'),
            'Franquicia Costa del Sol',
            'Avenida de Andalucía 118',
            '29006',
            'Málaga',
            'ES',
            $urgency,
            $due ?? new \DateTimeImmutable('2026-11-08 14:00', new \DateTimeZone('Europe/Madrid')),
            $lines ?? [new ErpOrderLine(1, 'PV27-CAM-LIN-AZU-S', 4), new ErpOrderLine(2, 'PV27-PAN-CHI-ARE-40', 1)],
        );
    }

    private function translator(): ErpOrderTranslator
    {
        $products = [
            'PV27-CAM-LIN-AZU-S' => $this->snapshot($this->camisaId, 'PV27-CAM-LIN-AZU-S'),
            'PV27-PAN-CHI-ARE-40' => $this->snapshot($this->pantalonId, 'PV27-PAN-CHI-ARE-40'),
        ];

        return new ErpOrderTranslator(new readonly class($this->tenant, $products) implements ProductCatalog {
            /**
             * @param array<string, ProductSnapshot> $products the products of the tenant, by SKU
             */
            public function __construct(private TenantId $tenant, private array $products)
            {
            }

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
                if (1 !== preg_match('/^[A-Z0-9-]+$/', $sku)) {
                    throw InvalidSku::forValue($sku);
                }

                return $tenantId->equals($this->tenant) ? ($this->products[$sku] ?? null) : null;
            }
        });
    }

    private function snapshot(string $id, string $sku): ProductSnapshot
    {
        return new ProductSnapshot($id, $sku, 'Producto', StorageClass::Ambient, false, false, null, null, true, null, TurnoverClass::B, 350, 250, 40, 250);
    }
}
