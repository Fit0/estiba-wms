<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Domain;

use App\Inventory\Domain\AdjustmentReason;
use App\Inventory\Domain\LotNumber;
use App\Inventory\Domain\MovementReference;
use App\Inventory\Domain\Quantity;
use App\Tests\Unit\Support\AssertsDomainErrors;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ValueObjectsTest extends TestCase
{
    use AssertsDomainErrors;

    public function testAQuantityIsZeroOrMore(): void
    {
        self::assertSame(0, Quantity::zero()->units);
        self::assertTrue(Quantity::of(0)->isZero());
        self::assertSame(12, Quantity::of(12)->units);
    }

    // STK-01
    public function testRejectsANegativeQuantity(): void
    {
        self::assertDomainError('inventory.quantity_invalid', ['units' => -1], static fn (): Quantity => Quantity::of(-1));
    }

    public function testAddsQuantities(): void
    {
        self::assertSame(15, Quantity::of(10)->plus(Quantity::of(5))->units);
    }

    // STK-01
    public function testSubtractsQuantitiesDownToZeroButNotBelow(): void
    {
        self::assertTrue(Quantity::of(5)->minus(Quantity::of(5))->isZero());

        self::assertDomainError('inventory.stock_negative', ['requested' => 6, 'onHand' => 5], static fn (): Quantity => Quantity::of(5)->minus(Quantity::of(6)));
    }

    public function testComparesQuantitiesByValue(): void
    {
        self::assertTrue(Quantity::of(3)->equals(Quantity::of(3)));
        self::assertFalse(Quantity::of(3)->equals(Quantity::of(4)));
    }

    public function testTrimsALotNumber(): void
    {
        self::assertSame('L-2027-01', LotNumber::fromString('  L-2027-01 ')->value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidLotNumbers(): iterable
    {
        yield 'empty' => [''];
        yield 'only spaces' => ['   '];
        yield 'too long' => [str_repeat('L', 41)];
        yield 'control character' => ["L\x07-1"];
    }

    #[DataProvider('invalidLotNumbers')]
    public function testRejectsAnInvalidLotNumber(string $value): void
    {
        self::assertDomainError('inventory.lot_number_invalid', ['value' => $value], static fn (): LotNumber => LotNumber::fromString($value));
    }

    public function testAcceptsTheLongestLotNumber(): void
    {
        self::assertSame(40, mb_strlen(LotNumber::fromString(str_repeat('L', 40))->value));
    }

    public function testKeepsTheReferenceAndTheReasonAsTyped(): void
    {
        self::assertSame('Albarán 2027/0153', MovementReference::fromString(' Albarán 2027/0153 ')->value);
        self::assertSame('Rotura en el traslado', AdjustmentReason::fromString('Rotura en el traslado')->value);
    }

    // STK-09
    public function testAnAdjustmentReasonCannotBeEmpty(): void
    {
        self::assertDomainError('inventory.movement_text_invalid', ['field' => 'reason', 'maxLength' => 200], static fn (): AdjustmentReason => AdjustmentReason::fromString('  '));
    }

    public function testLimitsTheLengthOfTheReasonAndTheReference(): void
    {
        self::assertDomainError('inventory.movement_text_invalid', ['field' => 'reason', 'maxLength' => 200], static fn (): AdjustmentReason => AdjustmentReason::fromString(str_repeat('a', 201)));
        self::assertDomainError('inventory.movement_text_invalid', ['field' => 'reference', 'maxLength' => 80], static fn (): MovementReference => MovementReference::fromString(str_repeat('a', 81)));
        self::assertSame(80, mb_strlen(MovementReference::fromString(str_repeat('a', 80))->value));
    }
}
