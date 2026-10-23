<?php

declare(strict_types=1);

namespace App\Tests\Unit\Outbound\Domain;

use App\Outbound\Domain\Event\PickListGenerated;
use App\Outbound\Domain\PickList;
use App\Outbound\Domain\PickListId;
use App\Outbound\Domain\PickListStatus;
use App\Outbound\Domain\PickRequest;
use App\Outbound\Domain\WaveId;
use App\Tests\Unit\Support\AssertsDomainErrors;
use App\Tests\Unit\Support\Ids;
use PHPUnit\Framework\TestCase;

final class PickListTest extends TestCase
{
    use AssertsDomainErrors;

    // OUT-09: the position of each stop is its place on the walk
    public function testNumbersTheStopsInTheOrderGiven(): void
    {
        $list = $this->generate([
            OutboundMother::pickRequest('A-01-01', units: 3),
            OutboundMother::pickRequest('A-01-02', units: 2),
            OutboundMother::pickRequest('B-01-02', units: 5),
        ]);

        self::assertSame([1, 2, 3], array_map(static fn ($line): int => $line->position, $list->lines()));
        self::assertSame(['A-01-01', 'A-01-02', 'B-01-02'], array_map(static fn ($line): string => $line->locationCode, $list->lines()));
        self::assertSame(10, $list->unitsToPick());
    }

    public function testEachStopAsksForWhatWasReserved(): void
    {
        $request = OutboundMother::pickRequest('A-01-01', orderLine: 2, units: 6, lot: 'L-9', expiryDate: new \DateTimeImmutable('2027-05-31'));

        $line = $this->generate([$request])->lines()[0];

        self::assertTrue($request->orderId->equals($line->orderId));
        self::assertSame(2, $line->orderLineNumber);
        self::assertTrue($request->productId->equals($line->productId));
        self::assertSame($request->locationId, $line->locationId);
        self::assertSame('L-9', $line->lot);
        self::assertSame('2027-05-31', $line->expiryDate?->format('Y-m-d'));
        self::assertSame(6, $line->quantityToPick);
        self::assertSame(0, $line->quantityPicked, 'Nothing is picked yet.');
    }

    public function testStartsGeneratedAndRaisesAnEvent(): void
    {
        $list = $this->generate([OutboundMother::pickRequest('A-01-01')]);

        self::assertSame(PickListStatus::Generated, $list->status());
        $events = $list->pullEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(PickListGenerated::class, $events[0]);
        self::assertTrue($list->id()->equals($events[0]->pickListId));
        self::assertTrue($list->waveId()->equals($events[0]->waveId));
    }

    public function testAListNeedsAtLeastOneStop(): void
    {
        self::assertDomainError('outbound.pick_list_invalid', ['reason' => 'empty'], fn (): PickList => $this->generate([]));
    }

    public function testAStopAsksForAtLeastOneUnit(): void
    {
        self::assertDomainError('outbound.pick_list_invalid', ['reason' => 'no_units'], fn (): PickList => $this->generate([OutboundMother::pickRequest('A-01-01'), OutboundMother::pickRequest('A-01-02', units: 0)]));
    }

    public function testCancellingCallsTheListOffAndRepeatingItChangesNothing(): void
    {
        $list = $this->generate([OutboundMother::pickRequest('A-01-01')]);

        $list->cancel();
        $list->cancel();

        self::assertSame(PickListStatus::Cancelled, $list->status());
    }

    /**
     * @param list<PickRequest> $route
     */
    private function generate(array $route): PickList
    {
        return PickList::generate(PickListId::fromString(Ids::next()), OutboundMother::tenantId(), WaveId::fromString(Ids::next()), $route, OutboundMother::at());
    }
}
