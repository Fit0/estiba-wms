<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Domain;

use App\Inventory\Domain\AdjustmentReason;
use App\Inventory\Domain\Event\StockMovementRecorded;
use App\Inventory\Domain\MovementReference;
use App\Inventory\Domain\MovementType;
use App\Inventory\Domain\Quantity;
use App\Inventory\Domain\StockItem;
use App\Inventory\Domain\StockMovement;
use App\Inventory\Domain\StockStatus;
use App\Tests\Unit\Support\AssertsDomainErrors;
use PHPUnit\Framework\TestCase;

final class StockItemTest extends TestCase
{
    use AssertsDomainErrors;

    public function testANewStockItemIsEmptyAndRecordsNothing(): void
    {
        $stockItem = StockMother::stockItem();

        self::assertTrue($stockItem->onHand()->isZero());
        self::assertSame([], $stockItem->pullMovements());
        self::assertSame([], $stockItem->pullEvents());
    }

    // STK-03
    public function testAReceiptAddsUnitsAndRecordsOneMovement(): void
    {
        $stockItem = StockMother::stockItem();
        $author = StockMother::author();
        $movementId = StockMother::movementId();

        $stockItem->receive($movementId, Quantity::of(40), MovementReference::fromString('ALB-2027-0153'), $author, StockMother::at());

        self::assertSame(40, $stockItem->onHand()->units);
        $movements = $stockItem->pullMovements();
        self::assertCount(1, $movements);
        self::assertSame(MovementType::Receipt, $movements[0]->type);
        self::assertSame(40, $movements[0]->change);
        self::assertSame(40, $movements[0]->balanceAfter->units);
        self::assertSame('ALB-2027-0153', $movements[0]->reference?->value);
        self::assertNull($movements[0]->reason);
        self::assertTrue($movementId->equals($movements[0]->id));
        self::assertTrue($author->equals($movements[0]->author));
        self::assertTrue($stockItem->id()->equals($movements[0]->stockItemId));
        self::assertTrue($stockItem->tenantId()->equals($movements[0]->tenantId));
        self::assertEquals(StockMother::at(), $movements[0]->occurredAt);
    }

    public function testEachMovementRaisesAnEvent(): void
    {
        $stockItem = StockMother::stockItem();
        $movementId = StockMother::movementId();

        $stockItem->receive($movementId, Quantity::of(5), null, StockMother::author(), StockMother::at());

        $events = $stockItem->pullEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(StockMovementRecorded::class, $events[0]);
        self::assertSame(MovementType::Receipt, $events[0]->type);
        self::assertSame(5, $events[0]->change);
        self::assertTrue($movementId->equals($events[0]->movementId));
        self::assertTrue($stockItem->tenantId()->equals($events[0]->tenantId), 'Its policies run where no user is signed in.');
        self::assertEquals(StockMother::at(), $events[0]->occurredAt());
    }

    public function testAnIssueTakesUnitsAndRecordsANegativeChange(): void
    {
        $stockItem = StockMother::stockItemHolding(40);

        $stockItem->issue(StockMother::movementId(), Quantity::of(15), MovementReference::fromString('PED-9001'), StockMother::author(), StockMother::at());

        self::assertSame(25, $stockItem->onHand()->units);
        $movements = $stockItem->pullMovements();
        self::assertCount(1, $movements);
        self::assertSame(MovementType::Issue, $movements[0]->type);
        self::assertSame(-15, $movements[0]->change);
        self::assertSame(25, $movements[0]->balanceAfter->units);
    }

    // STK-01
    public function testAnIssueCanTakeEverythingButNotMore(): void
    {
        $stockItem = StockMother::stockItemHolding(10);

        $stockItem->issue(StockMother::movementId(), Quantity::of(10), null, StockMother::author(), StockMother::at());
        self::assertTrue($stockItem->onHand()->isZero());

        self::assertDomainError('inventory.stock_negative', ['requested' => 1, 'onHand' => 0], static fn () => $stockItem->issue(StockMother::movementId(), Quantity::of(1), null, StockMother::author(), StockMother::at()));
    }

    // STK-01, STK-03
    public function testARefusedIssueChangesNothingAndRecordsNothing(): void
    {
        $stockItem = StockMother::stockItemHolding(10);

        self::assertDomainError('inventory.stock_negative', ['requested' => 11, 'onHand' => 10], static fn () => $stockItem->issue(StockMother::movementId(), Quantity::of(11), null, StockMother::author(), StockMother::at()));

        self::assertSame(10, $stockItem->onHand()->units);
        self::assertSame([], $stockItem->pullMovements());
        self::assertSame([], $stockItem->pullEvents());
    }

    // STK-03
    public function testAMovementOfZeroUnitsIsRefused(): void
    {
        $stockItem = StockMother::stockItemHolding(10);

        self::assertDomainError('inventory.movement_quantity_not_positive', ['type' => 'receipt'], static fn () => $stockItem->receive(StockMother::movementId(), Quantity::zero(), null, StockMother::author(), StockMother::at()));
        self::assertDomainError('inventory.movement_quantity_not_positive', ['type' => 'issue'], static fn () => $stockItem->issue(StockMother::movementId(), Quantity::zero(), null, StockMother::author(), StockMother::at()));
        self::assertDomainError('inventory.movement_quantity_not_positive', ['type' => 'adjustment'], static fn () => $stockItem->adjust(StockMother::movementId(), 0, AdjustmentReason::fromString('Recuento'), StockMother::author(), StockMother::at()));
        self::assertSame([], $stockItem->pullMovements());
    }

    // STK-09
    public function testAnAdjustmentKeepsItsReasonAndItsAuthor(): void
    {
        $stockItem = StockMother::stockItemHolding(10);
        $author = StockMother::author();

        $stockItem->adjust(StockMother::movementId(), -3, AdjustmentReason::fromString('Recuento: faltan 3 prendas'), $author, StockMother::at());

        self::assertSame(7, $stockItem->onHand()->units);
        $movements = $stockItem->pullMovements();
        self::assertSame(MovementType::Adjustment, $movements[0]->type);
        self::assertSame(-3, $movements[0]->change);
        self::assertSame('Recuento: faltan 3 prendas', $movements[0]->reason?->value);
        self::assertTrue($author->equals($movements[0]->author));
    }

    public function testAnAdjustmentCanAddUnits(): void
    {
        $stockItem = StockMother::stockItemHolding(10);

        $stockItem->adjust(StockMother::movementId(), 4, AdjustmentReason::fromString('Recuento: sobran 4'), StockMother::author(), StockMother::at());

        self::assertSame(14, $stockItem->onHand()->units);
        self::assertSame(4, $stockItem->pullMovements()[0]->change);
    }

    // STK-01
    public function testAnAdjustmentCannotMakeStockNegative(): void
    {
        $stockItem = StockMother::stockItemHolding(2);

        self::assertDomainError('inventory.stock_negative', ['requested' => 3, 'onHand' => 2], static fn () => $stockItem->adjust(StockMother::movementId(), -3, AdjustmentReason::fromString('Recuento'), StockMother::author(), StockMother::at()));

        self::assertSame(2, $stockItem->onHand()->units);
        self::assertSame([], $stockItem->pullMovements());
    }

    // STK-08
    public function testATransferTakesFromOneStockItemAndAddsToTheOther(): void
    {
        $tenantId = StockMother::tenantId();
        $productId = StockMother::productId();
        $source = StockMother::stockItemHolding(30, $tenantId, StockMother::key($productId));
        $destination = StockMother::stockItemHolding(5, $tenantId, StockMother::key($productId, status: StockStatus::Quarantine));

        $source->transferTo($destination, Quantity::of(12), StockMother::movementId(), StockMother::movementId(), MovementReference::fromString('TRAS-01'), StockMother::author(), StockMother::at());

        self::assertSame(18, $source->onHand()->units);
        self::assertSame(17, $destination->onHand()->units);

        $out = $source->pullMovements();
        $in = $destination->pullMovements();
        self::assertCount(1, $out);
        self::assertCount(1, $in);
        self::assertSame(MovementType::TransferOut, $out[0]->type);
        self::assertSame(-12, $out[0]->change);
        self::assertSame(MovementType::TransferIn, $in[0]->type);
        self::assertSame(12, $in[0]->change);
        self::assertSame(17, $in[0]->balanceAfter->units);
        self::assertSame('TRAS-01', $out[0]->reference?->value);
        self::assertSame('TRAS-01', $in[0]->reference?->value);
        self::assertTrue($destination->id()->equals($in[0]->stockItemId));
    }

    // STK-08, STK-01
    public function testATransferThatCannotBeCoveredChangesNeitherStockItem(): void
    {
        $tenantId = StockMother::tenantId();
        $productId = StockMother::productId();
        $source = StockMother::stockItemHolding(3, $tenantId, StockMother::key($productId));
        $destination = StockMother::stockItemHolding(5, $tenantId, StockMother::key($productId));

        self::assertDomainError('inventory.stock_negative', ['requested' => 4, 'onHand' => 3], static fn () => $source->transferTo($destination, Quantity::of(4), StockMother::movementId(), StockMother::movementId(), null, StockMother::author(), StockMother::at()));

        self::assertSame(3, $source->onHand()->units);
        self::assertSame(5, $destination->onHand()->units);
        self::assertSame([], $source->pullMovements());
        self::assertSame([], $destination->pullMovements());
    }

    // STK-08
    public function testATransferToItselfIsRefused(): void
    {
        $stockItem = StockMother::stockItemHolding(10);

        self::assertDomainError('inventory.transfer_same_stock_item', ['stockItemId' => $stockItem->id()->value], static fn () => $stockItem->transferTo($stockItem, Quantity::of(1), StockMother::movementId(), StockMother::movementId(), null, StockMother::author(), StockMother::at()));
        self::assertSame(10, $stockItem->onHand()->units);
    }

    // STK-08
    public function testATransferBetweenDifferentProductsIsRefused(): void
    {
        $tenantId = StockMother::tenantId();
        $source = StockMother::stockItemHolding(10, $tenantId);
        $destination = StockMother::stockItemHolding(10, $tenantId);

        self::assertDomainError(
            'inventory.transfer_product_mismatch',
            ['sourceProductId' => $source->key()->productId->value, 'destinationProductId' => $destination->key()->productId->value],
            static fn () => $source->transferTo($destination, Quantity::of(1), StockMother::movementId(), StockMother::movementId(), null, StockMother::author(), StockMother::at()),
        );
        self::assertSame(10, $source->onHand()->units);
    }

    // STK-08
    public function testATransferAcrossTenantsIsRefused(): void
    {
        $productId = StockMother::productId();
        $source = StockMother::stockItemHolding(10, StockMother::tenantId(), StockMother::key($productId));
        $destination = StockMother::stockItemHolding(10, StockMother::tenantId(), StockMother::key($productId));

        self::assertDomainError('inventory.transfer_across_tenants', [], static fn () => $source->transferTo($destination, Quantity::of(1), StockMother::movementId(), StockMother::movementId(), null, StockMother::author(), StockMother::at()));
        self::assertSame(10, $source->onHand()->units);
        self::assertSame(10, $destination->onHand()->units);
    }

    // STK-03
    public function testATransferOfZeroUnitsIsRefused(): void
    {
        $tenantId = StockMother::tenantId();
        $productId = StockMother::productId();
        $source = StockMother::stockItemHolding(10, $tenantId, StockMother::key($productId));
        $destination = StockMother::stockItem($tenantId, StockMother::key($productId, status: StockStatus::Damaged));

        self::assertDomainError('inventory.movement_quantity_not_positive', ['type' => 'transfer_out'], static fn () => $source->transferTo($destination, Quantity::zero(), StockMother::movementId(), StockMother::movementId(), null, StockMother::author(), StockMother::at()));
    }

    // STK-04
    public function testAMovementCannotBeChangedOnceRecorded(): void
    {
        $reflection = new \ReflectionClass(StockMovement::class);

        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly(), 'Every property of a movement is read-only.');
        self::assertSame([], array_filter($reflection->getMethods(\ReflectionMethod::IS_PUBLIC), static fn (\ReflectionMethod $method): bool => !$method->isConstructor()), 'A movement has no method that changes it.');
    }

    public function testMovementsAreTakenOnce(): void
    {
        $stockItem = StockMother::stockItem();
        $stockItem->receive(StockMother::movementId(), Quantity::of(1), null, StockMother::author(), StockMother::at());

        self::assertCount(1, $stockItem->pullMovements());
        self::assertSame([], $stockItem->pullMovements(), 'The repository stores each movement once.');
    }

    // STK-05
    public function testTheSumOfTheMovementsIsAlwaysWhatIsOnHand(): void
    {
        $tenantId = StockMother::tenantId();
        $productId = StockMother::productId();
        $a = StockMother::stockItem($tenantId, StockMother::key($productId));
        $b = StockMother::stockItem($tenantId, StockMother::key($productId, status: StockStatus::Quarantine));
        $author = StockMother::author();
        $at = StockMother::at();

        $a->receive(StockMother::movementId(), Quantity::of(100), null, $author, $at);
        $a->issue(StockMother::movementId(), Quantity::of(17), null, $author, $at);
        $a->transferTo($b, Quantity::of(30), StockMother::movementId(), StockMother::movementId(), null, $author, $at);
        $b->adjust(StockMother::movementId(), -4, AdjustmentReason::fromString('Prendas dañadas'), $author, $at);
        $b->transferTo($a, Quantity::of(6), StockMother::movementId(), StockMother::movementId(), null, $author, $at);
        $a->adjust(StockMother::movementId(), 2, AdjustmentReason::fromString('Recuento'), $author, $at);

        foreach ([$a, $b] as $stockItem) {
            $movements = $stockItem->pullMovements();
            self::assertSame($stockItem->onHand()->units, array_sum(array_map(static fn (StockMovement $movement): int => $movement->change, $movements)));
            $last = end($movements);
            self::assertInstanceOf(StockMovement::class, $last);
            self::assertSame($stockItem->onHand()->units, $last->balanceAfter->units);
        }
        self::assertSame(100 - 17 - 30 + 6 + 2, $a->onHand()->units);
        self::assertSame(30 - 4 - 6, $b->onHand()->units);
    }

    // STK-05
    public function testEveryBalanceRecordedFollowsFromThePreviousOne(): void
    {
        $stockItem = StockMother::stockItem();
        $author = StockMother::author();
        $at = StockMother::at();

        foreach ([50, -20, 7, -37, 3] as $change) {
            if ($change > 0) {
                $stockItem->receive(StockMother::movementId(), Quantity::of($change), null, $author, $at);
            } else {
                $stockItem->issue(StockMother::movementId(), Quantity::of(-$change), null, $author, $at);
            }
        }

        $balance = 0;
        foreach ($stockItem->pullMovements() as $movement) {
            $balance += $movement->change;
            self::assertSame($balance, $movement->balanceAfter->units);
            self::assertGreaterThanOrEqual(0, $movement->balanceAfter->units, 'STK-01: no balance in the ledger is negative.');
        }
        self::assertSame(3, $balance);
    }

    public function testStockItemKeepsItsKey(): void
    {
        $key = StockMother::key(lot: 'L-9', expiryDate: new \DateTimeImmutable('2027-12-31'));
        $stockItem = StockItem::open(StockMother::stockItem()->id(), StockMother::tenantId(), $key);

        self::assertSame($key, $stockItem->key());
        self::assertSame('2027-12-31', $stockItem->key()->expiryDate?->format('Y-m-d'));
    }
}
