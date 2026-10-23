<?php

declare(strict_types=1);

namespace App\Tests\Unit\Outbound\Domain;

use App\Outbound\Domain\PickRequest;
use App\Outbound\Domain\SerpentineRoute;
use PHPUnit\Framework\TestCase;

/**
 * The walk goes up one aisle and down the next, and never backtracks (OUT-09).
 */
final class SerpentineRouteTest extends TestCase
{
    private SerpentineRoute $route;

    #[\Override]
    protected function setUp(): void
    {
        $this->route = new SerpentineRoute();
    }

    public function testOneAisleIsWalkedInTheOrderOfThePickSequence(): void
    {
        $route = $this->route->order([
            OutboundMother::pickRequest('A-01-03', pickSequence: 30),
            OutboundMother::pickRequest('A-01-01', pickSequence: 10),
            OutboundMother::pickRequest('A-01-02', pickSequence: 20),
        ]);

        self::assertSame(['A-01-01', 'A-01-02', 'A-01-03'], $this->codes($route));
    }

    // OUT-09: the first aisle one way, the second the other way
    public function testTheSecondAisleIsWalkedBackwards(): void
    {
        $route = $this->route->order([
            OutboundMother::pickRequest('A-01-01', pickSequence: 10),
            OutboundMother::pickRequest('A-01-02', pickSequence: 20),
            OutboundMother::pickRequest('B-01-01', pickSequence: 30),
            OutboundMother::pickRequest('B-01-02', pickSequence: 40),
        ]);

        self::assertSame(['A-01-01', 'A-01-02', 'B-01-02', 'B-01-01'], $this->codes($route));
    }

    public function testTheThirdAisleIsWalkedForwardsAgain(): void
    {
        $route = $this->route->order([
            OutboundMother::pickRequest('A-01-01', pickSequence: 10),
            OutboundMother::pickRequest('A-01-02', pickSequence: 20),
            OutboundMother::pickRequest('B-01-01', pickSequence: 30),
            OutboundMother::pickRequest('B-01-02', pickSequence: 40),
            OutboundMother::pickRequest('C-01-01', pickSequence: 50),
            OutboundMother::pickRequest('C-01-02', pickSequence: 60),
            OutboundMother::pickRequest('D-01-01', pickSequence: 70),
            OutboundMother::pickRequest('D-01-02', pickSequence: 80),
        ]);

        self::assertSame(['A-01-01', 'A-01-02', 'B-01-02', 'B-01-01', 'C-01-01', 'C-01-02', 'D-01-02', 'D-01-01'], $this->codes($route));
    }

    // An aisle with nothing to pick is not walked, so it does not turn the next one around
    public function testAislesWithNothingToPickDoNotCount(): void
    {
        $route = $this->route->order([
            OutboundMother::pickRequest('A-01-01', pickSequence: 10),
            OutboundMother::pickRequest('A-01-02', pickSequence: 20),
            OutboundMother::pickRequest('D-01-01', pickSequence: 70),
            OutboundMother::pickRequest('D-01-02', pickSequence: 80),
        ]);

        self::assertSame(['A-01-01', 'A-01-02', 'D-01-02', 'D-01-01'], $this->codes($route), 'D is the second aisle visited, so it is walked backwards.');
    }

    public function testAislesAreWalkedAlphabeticallyAndTwoLettersComeAfterOne(): void
    {
        $route = $this->route->order([
            OutboundMother::pickRequest('AA-01-01', pickSequence: 90),
            OutboundMother::pickRequest('B-01-01', pickSequence: 20),
            OutboundMother::pickRequest('A-01-01', pickSequence: 10),
        ]);

        self::assertSame(['A-01-01', 'B-01-01', 'AA-01-01'], $this->codes($route));
    }

    public function testZonesAreWalkedInTheOrderOfTheirPickSequenceWhateverTheAisleLetters(): void
    {
        $route = $this->route->order([
            OutboundMother::pickRequest('A-01-01', zonePickSequence: 2, pickSequence: 10),
            OutboundMother::pickRequest('Z-01-01', zonePickSequence: 1, pickSequence: 10),
        ]);

        self::assertSame(['Z-01-01', 'A-01-01'], $this->codes($route));
    }

    public function testTheAlternationContinuesAcrossZones(): void
    {
        $route = $this->route->order([
            OutboundMother::pickRequest('A-01-01', zonePickSequence: 1, pickSequence: 10),
            OutboundMother::pickRequest('A-01-02', zonePickSequence: 1, pickSequence: 20),
            OutboundMother::pickRequest('F-01-01', zonePickSequence: 2, pickSequence: 10),
            OutboundMother::pickRequest('F-01-02', zonePickSequence: 2, pickSequence: 20),
        ]);

        self::assertSame(['A-01-01', 'A-01-02', 'F-01-02', 'F-01-01'], $this->codes($route), 'The operator is at the far end of the last aisle, so the next one is walked back.');
    }

    public function testPicksFromTheSameLocationStayTogetherInTheOrderOfTheirOrderLines(): void
    {
        $first = OutboundMother::pickRequest('A-01-01', pickSequence: 10, orderLine: 2);
        $second = OutboundMother::pickRequest('A-01-01', pickSequence: 10, orderLine: 1, orderId: $first->orderId);
        $elsewhere = OutboundMother::pickRequest('A-01-02', pickSequence: 20);

        $route = $this->route->order([$elsewhere, $first, $second]);

        self::assertSame([1, 2], [$route[0]->orderLineNumber, $route[1]->orderLineNumber]);
        self::assertSame('A-01-02', $route[2]->locationCode);
    }

    public function testTheSameRequestsAlwaysGiveTheSameRouteWhateverTheirOrder(): void
    {
        $requests = [
            OutboundMother::pickRequest('A-01-01', pickSequence: 10),
            OutboundMother::pickRequest('A-01-02', pickSequence: 20),
            OutboundMother::pickRequest('B-01-01', pickSequence: 30),
            OutboundMother::pickRequest('B-01-01', pickSequence: 30),
            OutboundMother::pickRequest('C-01-04', pickSequence: 55),
        ];

        $expected = $this->route->order($requests);

        self::assertEquals($expected, $this->route->order(array_reverse($requests)));
        self::assertEquals($expected, $this->route->order([$requests[3], $requests[0], $requests[4], $requests[2], $requests[1]]));
    }

    public function testDoesNotLoseOrInventPicks(): void
    {
        $requests = [
            OutboundMother::pickRequest('B-01-01', pickSequence: 30),
            OutboundMother::pickRequest('A-01-01', pickSequence: 10),
            OutboundMother::pickRequest('C-02-01', pickSequence: 60),
        ];

        $route = $this->route->order($requests);

        self::assertCount(3, $route);
        self::assertEqualsCanonicalizing(array_map(static fn (PickRequest $r): string => $r->orderId->value, $requests), array_map(static fn (PickRequest $r): string => $r->orderId->value, $route));
    }

    public function testNothingToPickIsNoRoute(): void
    {
        self::assertSame([], $this->route->order([]));
    }

    /**
     * @param list<PickRequest> $route
     *
     * @return list<string>
     */
    private function codes(array $route): array
    {
        return array_map(static fn (PickRequest $request): string => $request->locationCode, $route);
    }
}
