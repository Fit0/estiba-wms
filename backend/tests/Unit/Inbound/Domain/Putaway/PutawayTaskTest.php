<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inbound\Domain\Putaway;

use App\Inbound\Domain\AsnReference;
use App\Inbound\Domain\Event\PutawayConfirmed;
use App\Inbound\Domain\Event\PutawayTaskPlanned;
use App\Inbound\Domain\Event\PutawayTaskSplit;
use App\Inbound\Domain\LocationId;
use App\Inbound\Domain\LotNumber;
use App\Inbound\Domain\OverrideReason;
use App\Inbound\Domain\ProductId;
use App\Inbound\Domain\Putaway\PutawayDestination;
use App\Inbound\Domain\PutawayTask;
use App\Inbound\Domain\PutawayTaskId;
use App\Inbound\Domain\PutawayTaskStatus;
use App\Inbound\Domain\ReceivedGoods;
use App\Tests\Unit\Inbound\Domain\InboundMother;
use App\Tests\Unit\Support\AssertsDomainErrors;
use App\Tests\Unit\Support\Ids;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PutawayTaskTest extends TestCase
{
    use AssertsDomainErrors;

    private LocationId $dock;

    private LocationId $suggested;

    #[\Override]
    protected function setUp(): void
    {
        $this->dock = InboundMother::dock();
        $this->suggested = LocationId::fromString(Ids::next());
    }

    public function testIsPlannedForTheGoodsWithASuggestion(): void
    {
        $task = $this->task();

        self::assertSame(PutawayTaskStatus::Pending, $task->status());
        self::assertSame(40, $task->goods()->units);
        self::assertSame('L-0425', $task->goods()->lot?->value);
        self::assertTrue($task->sourceLocationId()->equals($this->dock));
        self::assertTrue($this->suggested->equals($task->suggestedLocationId() ?? $this->dock));
        self::assertInstanceOf(PutawayTaskPlanned::class, $task->pullEvents()[0] ?? null);
    }

    public function testIsConfirmedAtTheSuggestedLocation(): void
    {
        $task = $this->task();
        $task->pullEvents();

        $task->confirm($this->destination($this->suggested), null, InboundMother::operator(), InboundMother::at());

        self::assertSame(PutawayTaskStatus::Confirmed, $task->status());
        self::assertTrue($this->suggested->equals($task->confirmedLocationId() ?? $this->dock));
        self::assertNull($task->overrideReason());
        self::assertTrue(InboundMother::operator()->equals($task->confirmedBy() ?? InboundMother::asnId()));
        self::assertEquals(InboundMother::at(), $task->confirmedAt());

        $events = $task->pullEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(PutawayConfirmed::class, $events[0]);
        self::assertTrue($events[0]->fromLocationId->equals($this->dock));
        self::assertTrue($events[0]->toLocationId->equals($this->suggested));
        self::assertSame(40, $events[0]->goods->units);
        self::assertSame('ALB-2027-0153', $events[0]->reference->value);
    }

    // INB-13
    public function testAnotherLocationNeedsAReason(): void
    {
        $task = $this->task();
        $elsewhere = LocationId::fromString(Ids::next());

        self::assertDomainError('inbound.putaway_override_reason_required', ['suggestedLocationId' => $this->suggested->value], fn () => $task->confirm($this->destination($elsewhere), null, InboundMother::operator(), InboundMother::at()));
        self::assertSame(PutawayTaskStatus::Pending, $task->status());

        $task->confirm($this->destination($elsewhere), OverrideReason::fromString('Hueco ocupado por un palé sin ubicar'), InboundMother::operator(), InboundMother::at());

        self::assertTrue($elsewhere->equals($task->confirmedLocationId() ?? $this->dock));
        self::assertSame('Hueco ocupado por un palé sin ubicar', $task->overrideReason()?->value);
    }

    // INB-13: without a suggestion the operator chooses, and needs no reason.
    public function testWithoutASuggestionAnySuitableLocationWillDo(): void
    {
        $task = $this->task(suggested: false);
        $chosen = LocationId::fromString(Ids::next());

        $task->confirm($this->destination($chosen), OverrideReason::fromString('Ignored'), InboundMother::operator(), InboundMother::at());

        self::assertTrue($chosen->equals($task->confirmedLocationId() ?? $this->dock));
        self::assertNull($task->overrideReason(), 'Nothing was overridden.');
    }

    /**
     * @return iterable<string, array{array{bool, bool, bool, bool}, string}>
     */
    public static function unsuitable(): iterable
    {
        yield 'blocked' => [[false, true, true, true], 'blocked'];
        yield 'not a storage location' => [[true, false, true, true], 'wrong_type'];
        yield 'another storage class' => [[true, true, false, true], 'storage_class'];
        yield 'full' => [[true, true, true, false], 'no_room'];
    }

    /**
     * INB-11.
     *
     * @param array{bool, bool, bool, bool} $facts
     */
    #[DataProvider('unsuitable')]
    public function testRefusesALocationThatCannotTakeTheGoods(array $facts, string $reason): void
    {
        $task = $this->task();
        $destination = new PutawayDestination($this->suggested, ...$facts);

        self::assertDomainError('inbound.putaway_destination_invalid', ['locationId' => $this->suggested->value, 'reason' => $reason], static fn () => $task->confirm($destination, null, InboundMother::operator(), InboundMother::at()));
        self::assertSame(PutawayTaskStatus::Pending, $task->status());
    }

    // INB-11
    public function testRefusesTheDockTheGoodsAreOn(): void
    {
        $task = $this->task();

        self::assertDomainError('inbound.putaway_destination_invalid', ['locationId' => $this->dock->value, 'reason' => 'same_as_source'], fn () => $task->confirm($this->destination($this->dock), OverrideReason::fromString('x'), InboundMother::operator(), InboundMother::at()));
    }

    public function testIsConfirmedOnce(): void
    {
        $task = $this->task();
        $task->confirm($this->destination($this->suggested), null, InboundMother::operator(), InboundMother::at());

        $parameters = ['id' => $task->id()->value];
        self::assertDomainError('inbound.putaway_task_already_confirmed', $parameters, fn () => $task->confirm($this->destination($this->suggested), null, InboundMother::operator(), InboundMother::at()));
        self::assertDomainError('inbound.putaway_task_already_confirmed', $parameters, static fn (): PutawayTask => $task->split(PutawayTaskId::fromString(Ids::next()), 1, InboundMother::at()));
    }

    // INB-12, INB-14: a split keeps every unit, in two tasks.
    public function testSplitsUnitsIntoANewTask(): void
    {
        $task = $this->task();
        $task->pullEvents();
        $newId = PutawayTaskId::fromString(Ids::next());

        $new = $task->split($newId, 15, InboundMother::at());

        self::assertSame(25, $task->goods()->units);
        self::assertSame(15, $new->goods()->units);
        self::assertTrue($new->id()->equals($newId));
        self::assertTrue($new->receiptId()->equals($task->receiptId()));
        self::assertEquals($task->goods()->lot, $new->goods()->lot);
        self::assertTrue($this->suggested->equals($new->suggestedLocationId() ?? $this->dock));
        self::assertInstanceOf(PutawayTaskSplit::class, $task->pullEvents()[0] ?? null);
    }

    // INB-12
    public function testASplitLeavesUnitsOnBothSides(): void
    {
        $task = $this->task();

        foreach ([0, 40, 41, -1] as $units) {
            self::assertDomainError('inbound.putaway_split_invalid', ['units' => $units, 'taskUnits' => 40], static fn (): PutawayTask => $task->split(PutawayTaskId::fromString(Ids::next()), $units, InboundMother::at()));
        }
    }

    private function task(bool $suggested = true): PutawayTask
    {
        return PutawayTask::plan(
            PutawayTaskId::fromString(Ids::next()),
            InboundMother::tenantId(),
            InboundMother::receiptId(),
            AsnReference::fromString('ALB-2027-0153'),
            new ReceivedGoods(ProductId::fromString(Ids::next()), 40, LotNumber::fromString('L-0425'), new \DateTimeImmutable('2027-03-31'), false),
            $this->dock,
            $suggested ? $this->suggested : null,
            InboundMother::at(),
        );
    }

    private function destination(LocationId $locationId): PutawayDestination
    {
        return new PutawayDestination($locationId, true, true, true, true);
    }
}
