<?php

declare(strict_types=1);

namespace App\Tests\Unit\Outbound\Domain;

use App\Outbound\Domain\Event\PickLineConfirmed;
use App\Outbound\Domain\Event\PickListCompleted;
use App\Outbound\Domain\Event\PickListStarted;
use App\Outbound\Domain\Event\ShortPickReported;
use App\Outbound\Domain\OperatorId;
use App\Outbound\Domain\PickLine;
use App\Outbound\Domain\PickList;
use App\Outbound\Domain\PickListStatus;
use App\Outbound\Domain\ScannedPick;
use App\Outbound\Domain\ShortPickReason;
use App\Shared\Domain\DomainEvent;
use App\Tests\Unit\Support\AssertsDomainErrors;
use PHPUnit\Framework\TestCase;

/**
 * Walking a pick list: who walks it (OUT-12), what is scanned (OUT-11), how many units are picked
 * (OUT-10) and when the list is done (OUT-13).
 */
final class PickListPickingTest extends TestCase
{
    use AssertsDomainErrors;

    // OUT-12
    public function testASupervisorCanAssignTheListUntilItIsStarted(): void
    {
        $list = $this->list();
        $first = OutboundMother::operatorId();
        $second = OutboundMother::operatorId();

        $list->assign($first);
        $list->assign($second);

        self::assertSame($second->value, $list->operatorId()?->value);
        self::assertSame(PickListStatus::Generated, $list->status());
    }

    // OUT-12
    public function testAnAssignedListCannotBeAssignedOnceStarted(): void
    {
        $list = $this->list();
        $operator = OutboundMother::operatorId();
        $list->start($operator, OutboundMother::at());

        self::assertDomainError('outbound.pick_list_already_started', ['id' => $list->id()->value], static fn () => $list->assign(OutboundMother::operatorId()));
        self::assertSame($operator->value, $list->operatorId()?->value);
    }

    public function testStartingAListNobodyHasBeenAssignedMakesItTheOperatorsOwn(): void
    {
        $list = $this->list();
        $operator = OutboundMother::operatorId();
        $list->pullEvents();

        $list->start($operator, OutboundMother::at());

        self::assertSame(PickListStatus::InProgress, $list->status());
        self::assertSame($operator->value, $list->operatorId()?->value);
        self::assertEquals(OutboundMother::at(), $list->startedAt());
        $events = $list->pullEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(PickListStarted::class, $events[0]);
    }

    // OUT-12
    public function testOnlyTheAssignedOperatorCanStartIt(): void
    {
        $list = $this->list();
        $assigned = OutboundMother::operatorId();
        $list->assign($assigned);

        self::assertDomainError('outbound.pick_list_already_assigned', ['operatorId' => $assigned->value], static fn () => $list->start(OutboundMother::operatorId(), OutboundMother::at()));
        self::assertSame(PickListStatus::Generated, $list->status());

        $list->start($assigned, OutboundMother::at());
        self::assertSame(PickListStatus::InProgress, $list->status());
    }

    public function testStartingAgainChangesNothing(): void
    {
        $list = $this->list();
        $operator = OutboundMother::operatorId();
        $list->start($operator, OutboundMother::at());
        $list->pullEvents();

        $list->start($operator, new \DateTimeImmutable('2026-10-25T09:00:00+00:00'));

        self::assertEquals(OutboundMother::at(), $list->startedAt());
        self::assertSame([], $list->pullEvents());
    }

    public function testACancelledListCannotBeStarted(): void
    {
        $list = $this->list();
        $list->cancel();

        self::assertDomainError('outbound.pick_list_not_in_progress', ['status' => 'cancelled'], static fn () => $list->start(OutboundMother::operatorId(), OutboundMother::at()));
    }

    public function testConfirmingAStopTakesTheUnitsAndKeepsTheListOpenUntilTheLastOne(): void
    {
        $list = $this->started($operator = OutboundMother::operatorId());
        $list->pullEvents();
        [$first] = $list->lines();

        $line = $list->confirmLine(1, OutboundMother::scanFor($first), 3, null, $operator, OutboundMother::at());

        self::assertTrue($line->confirmed);
        self::assertSame(3, $line->quantityPicked);
        self::assertSame(0, $line->missingUnits());
        self::assertSame(PickListStatus::InProgress, $list->status());
        self::assertSame(3, $list->unitsPicked());
        $events = $list->pullEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(PickLineConfirmed::class, $events[0]);
    }

    // OUT-13
    public function testTheListIsCompleteWhenEveryStopIsConfirmed(): void
    {
        $list = $this->started($operator = OutboundMother::operatorId());
        $list->pullEvents();

        foreach ($list->lines() as $line) {
            $list->confirmLine($line->position, OutboundMother::scanFor($line), $line->quantityToPick, null, $operator, OutboundMother::at());
        }

        self::assertSame(PickListStatus::Completed, $list->status());
        self::assertEquals(OutboundMother::at(), $list->completedAt());
        self::assertSame(5, $list->unitsPicked());
        $completed = array_filter($list->pullEvents(), static fn (DomainEvent $event): bool => $event instanceof PickListCompleted);
        self::assertCount(1, $completed);
    }

    // OUT-10
    public function testCannotPickMoreThanTheListAsksFor(): void
    {
        $list = $this->started($operator = OutboundMother::operatorId());
        [$first] = $list->lines();

        self::assertDomainError('outbound.pick_exceeds_requested', ['position' => 1, 'picked' => 4, 'toPick' => 3], static fn (): PickLine => $list->confirmLine(1, OutboundMother::scanFor($first), 4, null, $operator, OutboundMother::at()));
        self::assertFalse($list->lines()[0]->confirmed, 'A refused pick changes nothing.');
    }

    public function testCannotPickANegativeNumberOfUnits(): void
    {
        $list = $this->started($operator = OutboundMother::operatorId());

        self::assertDomainError('outbound.picked_quantity_invalid', ['units' => -1], static fn (): PickLine => $list->confirmLine(1, OutboundMother::scanFor($list->lines()[0]), -1, ShortPickReason::fromString('Nada'), $operator, OutboundMother::at()));
    }

    // OUT-10
    public function testPickingLessNeedsAReason(): void
    {
        $list = $this->started($operator = OutboundMother::operatorId());

        self::assertDomainError('outbound.short_pick_reason_required', ['position' => 1, 'picked' => 2, 'toPick' => 3], static fn (): PickLine => $list->confirmLine(1, OutboundMother::scanFor($list->lines()[0]), 2, null, $operator, OutboundMother::at()));
        self::assertFalse($list->lines()[0]->confirmed);
    }

    // OUT-10
    public function testAShortPickKeepsItsReasonAndReportsWhatIsMissing(): void
    {
        $list = $this->started($operator = OutboundMother::operatorId());
        $list->pullEvents();

        $line = $list->confirmLine(1, OutboundMother::scanFor($list->lines()[0]), 1, ShortPickReason::fromString('Solo había una en la balda'), $operator, OutboundMother::at());

        self::assertSame(2, $line->missingUnits());
        self::assertSame('Solo había una en la balda', $line->shortReason?->value);
        $short = array_values(array_filter($list->pullEvents(), static fn (DomainEvent $event): bool => $event instanceof ShortPickReported));
        self::assertCount(1, $short);
        self::assertSame(2, $short[0]->missingUnits);
    }

    public function testNothingFoundIsAShortPickOfEverything(): void
    {
        $list = $this->started($operator = OutboundMother::operatorId());

        $line = $list->confirmLine(1, OutboundMother::scanFor($list->lines()[0]), 0, ShortPickReason::fromString('Balda vacía'), $operator, OutboundMother::at());

        self::assertSame(0, $line->quantityPicked);
        self::assertSame(3, $line->missingUnits());
        self::assertTrue($line->confirmed);
    }

    public function testAFullPickKeepsNoReason(): void
    {
        $list = $this->started($operator = OutboundMother::operatorId());

        $line = $list->confirmLine(1, OutboundMother::scanFor($list->lines()[0]), 3, ShortPickReason::fromString('Sobra'), $operator, OutboundMother::at());

        self::assertNull($line->shortReason);
    }

    // OUT-11
    public function testTheLocationScannedMustBeTheOneOfTheLine(): void
    {
        $list = $this->started($operator = OutboundMother::operatorId());
        $line = $list->lines()[0];

        self::assertDomainError('outbound.pick_scan_mismatch', ['what' => 'location', 'position' => 1], static fn (): PickLine => $list->confirmLine(1, new ScannedPick('B-09-09', $line->productId, $line->lot), 3, null, $operator, OutboundMother::at()));
        self::assertFalse($list->lines()[0]->confirmed);
    }

    public function testTheLocationIsComparedWithoutCaseOrSpaces(): void
    {
        $list = $this->started($operator = OutboundMother::operatorId());
        $line = $list->lines()[0];

        $confirmed = $list->confirmLine(1, new ScannedPick(' '.strtolower($line->locationCode).' ', $line->productId, $line->lot), 3, null, $operator, OutboundMother::at());

        self::assertTrue($confirmed->confirmed);
    }

    // OUT-11
    public function testTheProductScannedMustBeTheOneOfTheLine(): void
    {
        $list = $this->started($operator = OutboundMother::operatorId());
        $line = $list->lines()[0];

        self::assertDomainError('outbound.pick_scan_mismatch', ['what' => 'product', 'position' => 1], static fn (): PickLine => $list->confirmLine(1, new ScannedPick($line->locationCode, OutboundMother::productId(), $line->lot), 3, null, $operator, OutboundMother::at()));
        self::assertDomainError('outbound.pick_scan_mismatch', ['what' => 'product', 'position' => 1], static fn (): PickLine => $list->confirmLine(1, new ScannedPick($line->locationCode, null, $line->lot), 3, null, $operator, OutboundMother::at()));
    }

    // OUT-11: the lot is checked when the line has one
    public function testTheLotScannedMustBeTheOneOfTheLineWhenItHasOne(): void
    {
        $list = $this->started($operator = OutboundMother::operatorId(), withLot: true);
        $line = $list->lines()[0];

        self::assertDomainError('outbound.pick_scan_mismatch', ['what' => 'lot', 'position' => 1], static fn (): PickLine => $list->confirmLine(1, new ScannedPick($line->locationCode, $line->productId, 'L-OTRO'), 3, null, $operator, OutboundMother::at()));
        self::assertDomainError('outbound.pick_scan_mismatch', ['what' => 'lot', 'position' => 1], static fn (): PickLine => $list->confirmLine(1, new ScannedPick($line->locationCode, $line->productId, null), 3, null, $operator, OutboundMother::at()));

        $confirmed = $list->confirmLine(1, new ScannedPick($line->locationCode, $line->productId, ' L-7 '), 3, null, $operator, OutboundMother::at());
        self::assertTrue($confirmed->confirmed);
    }

    public function testTheLotIsNotAskedForWhenTheLineHasNone(): void
    {
        $list = $this->started($operator = OutboundMother::operatorId());
        $line = $list->lines()[0];

        $confirmed = $list->confirmLine(1, new ScannedPick($line->locationCode, $line->productId, 'anything'), 3, null, $operator, OutboundMother::at());

        self::assertTrue($confirmed->confirmed);
    }

    public function testPicksAreConfirmedOnlyOnAListInProgress(): void
    {
        $list = $this->list();

        self::assertDomainError('outbound.pick_list_not_in_progress', ['status' => 'generated'], static fn (): PickLine => $list->confirmLine(1, OutboundMother::scanFor($list->lines()[0]), 3, null, OutboundMother::operatorId(), OutboundMother::at()));
    }

    // OUT-12
    public function testOnlyTheOperatorOfTheListConfirmsItsPicks(): void
    {
        $list = $this->started($operator = OutboundMother::operatorId());
        $stranger = OutboundMother::operatorId();

        self::assertDomainError('outbound.pick_list_already_assigned', ['operatorId' => $operator->value], static fn (): PickLine => $list->confirmLine(1, OutboundMother::scanFor($list->lines()[0]), 3, null, $stranger, OutboundMother::at()));
    }

    public function testEachStopIsConfirmedOnce(): void
    {
        $list = $this->started($operator = OutboundMother::operatorId());
        $scan = OutboundMother::scanFor($list->lines()[0]);
        $list->confirmLine(1, $scan, 3, null, $operator, OutboundMother::at());

        self::assertDomainError('outbound.pick_line_already_confirmed', ['position' => 1], static fn (): PickLine => $list->confirmLine(1, $scan, 3, null, $operator, OutboundMother::at()));
    }

    public function testAStopThatDoesNotExistIsRefused(): void
    {
        $list = $this->started($operator = OutboundMother::operatorId());

        self::assertDomainError('outbound.pick_line_not_found', ['position' => 9], static fn (): PickLine => $list->confirmLine(9, OutboundMother::scanFor($list->lines()[0]), 1, null, $operator, OutboundMother::at()));
    }

    public function testAListWithPicksCannotBeCalledOff(): void
    {
        $list = $this->started($operator = OutboundMother::operatorId());
        $list->confirmLine(1, OutboundMother::scanFor($list->lines()[0]), 3, null, $operator, OutboundMother::at());

        self::assertDomainError('outbound.pick_list_already_picked', ['id' => $list->id()->value], static fn () => $list->cancel());
        self::assertSame(PickListStatus::InProgress, $list->status());
    }

    public function testAListInProgressWithNoPickCanBeCalledOff(): void
    {
        $list = $this->started(OutboundMother::operatorId());

        $list->cancel();

        self::assertSame(PickListStatus::Cancelled, $list->status());
    }

    private function list(bool $withLot = false): PickList
    {
        return OutboundMother::pickList([
            OutboundMother::pickRequest('A-01-01', pickSequence: 10, units: 3, lot: $withLot ? 'L-7' : null),
            OutboundMother::pickRequest('A-01-02', pickSequence: 20, units: 2),
        ]);
    }

    private function started(OperatorId $operator, bool $withLot = false): PickList
    {
        $list = $this->list($withLot);
        $list->start($operator, OutboundMother::at());

        return $list;
    }
}
