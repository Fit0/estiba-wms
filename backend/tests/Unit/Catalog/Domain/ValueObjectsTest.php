<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain;

use App\Catalog\Domain\Dimensions;
use App\Catalog\Domain\PackagingLevel;
use App\Catalog\Domain\PackagingType;
use App\Catalog\Domain\ProductName;
use App\Catalog\Domain\Sku;
use App\Catalog\Domain\TrackingPolicy;
use App\Tests\Unit\Support\AssertsDomainErrors;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ValueObjectsTest extends TestCase
{
    use AssertsDomainErrors;

    #[DataProvider('validSkus')]
    public function testNormalizesAValidSku(string $input, string $expected): void
    {
        self::assertSame($expected, Sku::fromString($input)->value);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function validSkus(): iterable
    {
        yield 'upper case' => ['AGUA-500', 'AGUA-500'];
        yield 'lower case becomes upper case' => ['agua-500', 'AGUA-500'];
        yield 'spaces around are removed' => ['  ab-1 ', 'AB-1'];
        yield 'one character' => ['a', 'A'];
        yield 'dots, underscores and slashes' => ['AB.1_2/C', 'AB.1_2/C'];
        yield 'longest' => [str_repeat('A', 40), str_repeat('A', 40)];
    }

    // CAT-01: the format of a SKU.
    #[DataProvider('invalidSkus')]
    public function testRejectsASkuThatBreaksTheFormat(string $input): void
    {
        self::assertDomainError(
            'catalog.sku_invalid',
            ['value' => $input],
            static fn (): Sku => Sku::fromString($input),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidSkus(): iterable
    {
        yield 'empty' => [''];
        yield 'only spaces' => ['   '];
        yield 'inner space' => ['AB 1'];
        yield 'starts with a dash' => ['-AB'];
        yield 'ends with a dot' => ['AB.'];
        yield 'too long' => [str_repeat('A', 41)];
        yield 'accent' => ['ÁGUA'];
    }

    // CAT-01: case and surrounding spaces do not make a different SKU.
    public function testComparesSkusIgnoringCaseAndSurroundingSpaces(): void
    {
        self::assertTrue(Sku::fromString(' ab-1 ')->equals(Sku::fromString('AB-1')));
        self::assertFalse(Sku::fromString('AB-1')->equals(Sku::fromString('AB-2')));
    }

    public function testKeepsAProductNameAsTyped(): void
    {
        self::assertSame('Aceite de oliva "Hojiblanca" 1 l', ProductName::fromString('  Aceite de oliva "Hojiblanca" 1 l ')->value);
    }

    #[DataProvider('invalidNames')]
    public function testRejectsAnInvalidProductName(string $input): void
    {
        self::assertDomainError(
            'catalog.product_name_invalid',
            ['value' => $input],
            static fn (): ProductName => ProductName::fromString($input),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidNames(): iterable
    {
        yield 'empty' => [''];
        yield 'only spaces' => ['   '];
        yield 'control character' => ["Agua\nmineral"];
        yield 'too long' => [str_repeat('A', 151)];
    }

    public function testCalculatesTheVolumeOfTheBaseUnit(): void
    {
        $dimensions = Dimensions::of(120, 80, 60, 450);

        self::assertSame(576_000, $dimensions->volumeCubicMm());
        self::assertSame(450, $dimensions->weightGrams);
    }

    // CAT-09: dimensions and weight are positive whole numbers.
    #[DataProvider('invalidDimensions')]
    public function testRejectsADimensionThatIsNotPositive(int $length, int $width, int $height, int $weight, string $field, int $value): void
    {
        self::assertDomainError(
            'catalog.dimensions_invalid',
            ['field' => $field, 'value' => $value],
            static fn (): Dimensions => Dimensions::of($length, $width, $height, $weight),
        );
    }

    /**
     * @return iterable<string, array{int, int, int, int, string, int}>
     */
    public static function invalidDimensions(): iterable
    {
        yield 'zero length' => [0, 80, 60, 450, 'lengthMm', 0];
        yield 'negative width' => [120, -1, 60, 450, 'widthMm', -1];
        yield 'zero height' => [120, 80, 0, 450, 'heightMm', 0];
        yield 'zero weight' => [120, 80, 60, 0, 'weightGrams', 0];
    }

    // CAT-03: a packaging level holds a positive whole number of base units.
    public function testRejectsAPackagingLevelWithoutBaseUnits(): void
    {
        self::assertDomainError(
            'catalog.packaging_level_invalid',
            ['type' => 'case', 'baseUnits' => 0],
            static fn (): PackagingLevel => PackagingLevel::of(PackagingType::Case, 0),
        );
    }

    public function testBuildsAPackagingLevel(): void
    {
        $level = PackagingLevel::of(PackagingType::Pallet, 480);

        self::assertSame(PackagingType::Pallet, $level->type);
        self::assertSame(480, $level->baseUnits);
    }

    public function testBuildsATrackingPolicyThatTracksLotAndExpiry(): void
    {
        $policy = TrackingPolicy::of(true, true, 90, 30);

        self::assertTrue($policy->trackedByLot);
        self::assertTrue($policy->trackedByExpiry);
        self::assertSame(90, $policy->minShelfLifeAtReceiptDays);
        self::assertSame(30, $policy->minShelfLifeAtShipmentDays);
    }

    public function testBuildsATrackingPolicyThatTracksNothing(): void
    {
        $policy = TrackingPolicy::none();

        self::assertFalse($policy->trackedByLot);
        self::assertFalse($policy->trackedByExpiry);
        self::assertNull($policy->minShelfLifeAtReceiptDays);
    }

    public function testAllowsTrackingByLotWithoutExpiry(): void
    {
        $policy = TrackingPolicy::of(true, false);

        self::assertTrue($policy->trackedByLot);
        self::assertFalse($policy->trackedByExpiry);
    }

    // CAT-04: a product tracked by expiry date is also tracked by lot.
    public function testRejectsTrackingByExpiryWithoutTrackingByLot(): void
    {
        self::assertDomainError(
            'catalog.expiry_requires_lot_tracking',
            [],
            static fn (): TrackingPolicy => TrackingPolicy::of(false, true),
        );
    }

    // CAT-07: the minimum shelf life only applies to expiry-tracked products and is not negative.
    public function testRejectsAShelfLifeOnAProductNotTrackedByExpiry(): void
    {
        self::assertDomainError(
            'catalog.shelf_life_invalid',
            ['field' => 'minShelfLifeAtReceiptDays', 'value' => 30, 'trackedByExpiry' => false],
            static fn (): TrackingPolicy => TrackingPolicy::of(true, false, 30),
        );
    }

    public function testRejectsANegativeShelfLife(): void
    {
        self::assertDomainError(
            'catalog.shelf_life_invalid',
            ['field' => 'minShelfLifeAtShipmentDays', 'value' => -1, 'trackedByExpiry' => true],
            static fn (): TrackingPolicy => TrackingPolicy::of(true, true, 10, -1),
        );
    }

    public function testAcceptsAShelfLifeOfZeroDays(): void
    {
        self::assertSame(0, TrackingPolicy::of(true, true, 0, 0)->minShelfLifeAtShipmentDays);
    }
}
