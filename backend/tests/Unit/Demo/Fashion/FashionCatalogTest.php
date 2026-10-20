<?php

declare(strict_types=1);

namespace App\Tests\Unit\Demo\Fashion;

use App\Catalog\Domain\Dimensions;
use App\Catalog\Domain\Gtin;
use App\Catalog\Domain\ProductName;
use App\Catalog\Domain\Sku;
use App\Demo\Fashion\DemoProduct;
use App\Demo\Fashion\FashionCatalog;
use PHPUnit\Framework\TestCase;

/**
 * The demo catalog goes through the domain when it is loaded, so anything the domain would refuse
 * must be found here first, with a clear message, and not halfway through a load.
 */
final class FashionCatalogTest extends TestCase
{
    /** @var list<DemoProduct> */
    private array $products;

    #[\Override]
    protected function setUp(): void
    {
        $this->products = new FashionCatalog()->products();
    }

    public function testHasOneProductForEachSizeAndColourOfEachModel(): void
    {
        // 3x2 shirts, 4x2 trousers, 3x2 dresses, 3x2 jackets, 3x2 jumpers, 3x1 belts, 4x1 trainers.
        self::assertCount(39, $this->products);
    }

    public function testEverySkuIsValidAndUnique(): void
    {
        $skus = array_map(static fn (DemoProduct $product): string => Sku::fromString($product->sku)->value, $this->products);

        self::assertCount(\count($skus), array_unique($skus));
        self::assertContains('PV27-CAM-LIN-BLA-M', $skus);
    }

    public function testEveryGtinHasAValidCheckDigitAndIsUnique(): void
    {
        $gtins = array_map(static fn (DemoProduct $product): string => Gtin::fromString($product->gtin)->value, $this->products);

        self::assertCount(\count($gtins), array_unique($gtins));
    }

    public function testEveryNameAndSizeIsAcceptedByTheDomain(): void
    {
        foreach ($this->products as $product) {
            self::assertNotSame('', ProductName::fromString($product->name)->value);
            self::assertSame($product->weightGrams, Dimensions::of($product->lengthMm, $product->widthMm, $product->heightMm, $product->weightGrams)->weightGrams);
        }
    }

    public function testNamesAgreeInGenderWithTheGarment(): void
    {
        $names = array_column(array_map(static fn (DemoProduct $product): array => ['sku' => $product->sku, 'name' => $product->name], $this->products), 'name', 'sku');

        self::assertSame('Camisa de lino blanca, talla M', $names['PV27-CAM-LIN-BLA-M']);
        self::assertSame('Pantalón chino marino, talla 40', $names['PV27-PAN-CHI-MAR-40']);
    }

    public function testACaseHoldsFewerUnitsThanAPallet(): void
    {
        foreach ($this->products as $product) {
            self::assertGreaterThan(0, $product->unitsPerCase, $product->sku);
            self::assertLessThan($product->unitsPerPallet, $product->unitsPerCase, $product->sku);
            self::assertSame(0, $product->unitsPerPallet % $product->unitsPerCase, $product->sku.': a pallet is a whole number of cases');
        }
    }
}
