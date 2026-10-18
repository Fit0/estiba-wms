<?php

declare(strict_types=1);

namespace App\Tests\Unit\Warehouse\Domain;

use App\Tests\Unit\Support\AssertsDomainErrors;
use App\Warehouse\Domain\LocationCapacity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LocationCapacityTest extends TestCase
{
    use AssertsDomainErrors;

    public function testHoldsTheLimitsItWasGiven(): void
    {
        $capacity = LocationCapacity::of(maxWeightKg: 1000, maxVolumeLitres: 1500, maxPalletPositions: 2);

        self::assertSame(1000, $capacity->maxWeightKg);
        self::assertSame(1500, $capacity->maxVolumeLitres);
        self::assertSame(2, $capacity->maxPalletPositions);
        self::assertTrue($capacity->hasLimits());
    }

    public function testALimitLeftOutMeansNoLimitOfThatKind(): void
    {
        $capacity = LocationCapacity::of(maxPalletPositions: 1);

        self::assertNull($capacity->maxWeightKg);
        self::assertNull($capacity->maxVolumeLitres);
        self::assertTrue($capacity->hasLimits());
    }

    public function testCanBeUnlimited(): void
    {
        self::assertFalse(LocationCapacity::unlimited()->hasLimits());
        self::assertFalse(LocationCapacity::of()->hasLimits());
    }

    // WH-04: every declared limit is a positive whole number.
    /**
     * @param array<string, int> $limits
     */
    #[DataProvider('invalidLimits')]
    public function testRejectsALimitThatIsNotPositive(array $limits, string $field, int $value): void
    {
        self::assertDomainError(
            'warehouse.capacity_invalid',
            ['field' => $field, 'value' => $value],
            static fn (): LocationCapacity => LocationCapacity::of(...$limits),
        );
    }

    /**
     * @return iterable<string, array{array<string, int>, string, int}>
     */
    public static function invalidLimits(): iterable
    {
        yield 'zero weight' => [['maxWeightKg' => 0], 'maxWeightKg', 0];
        yield 'negative weight' => [['maxWeightKg' => -5], 'maxWeightKg', -5];
        yield 'zero volume' => [['maxVolumeLitres' => 0], 'maxVolumeLitres', 0];
        yield 'negative volume' => [['maxVolumeLitres' => -1], 'maxVolumeLitres', -1];
        yield 'zero pallet positions' => [['maxPalletPositions' => 0], 'maxPalletPositions', 0];
        yield 'one bad limit among good ones' => [['maxWeightKg' => 100, 'maxPalletPositions' => -2], 'maxPalletPositions', -2];
    }
}
