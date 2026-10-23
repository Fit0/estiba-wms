<?php

declare(strict_types=1);

namespace App\Tests\Unit\Outbound\Domain;

use App\Outbound\Domain\PickListSplitter;
use App\Outbound\Domain\PickRequest;
use App\Tests\Unit\Support\AssertsDomainErrors;
use PHPUnit\Framework\TestCase;

final class PickListSplitterTest extends TestCase
{
    use AssertsDomainErrors;

    private PickListSplitter $splitter;

    #[\Override]
    protected function setUp(): void
    {
        $this->splitter = new PickListSplitter();
    }

    public function testOneOperatorTakesTheWholeWalk(): void
    {
        $route = $this->route(5);

        $lists = $this->splitter->split($route, 1);

        self::assertSame([$route], $lists);
    }

    public function testSharesTheWalkInStretchesThatFollowEachOther(): void
    {
        $route = $this->route(6);

        $lists = $this->splitter->split($route, 3);

        self::assertCount(3, $lists);
        self::assertSame([2, 2, 2], array_map(count(...), $lists));
        self::assertSame($route, array_merge(...$lists), 'Together they are the route, in the same order.');
    }

    public function testTheFirstOperatorsTakeTheExtraPicksWhenItDoesNotDivideEvenly(): void
    {
        $lists = $this->splitter->split($this->route(7), 3);

        self::assertSame([3, 2, 2], array_map(count(...), $lists));
    }

    public function testThereAreNeverMoreListsThanPicks(): void
    {
        $lists = $this->splitter->split($this->route(2), 10);

        self::assertSame([1, 1], array_map(count(...), $lists));
    }

    public function testNothingToPickGivesNoLists(): void
    {
        self::assertSame([], $this->splitter->split([], 3));
    }

    public function testAtLeastOneAndAtMostFiftyLists(): void
    {
        self::assertDomainError('outbound.pick_list_count_invalid', ['count' => 0, 'max' => 50], fn (): array => $this->splitter->split($this->route(3), 0));
        self::assertDomainError('outbound.pick_list_count_invalid', ['count' => -2, 'max' => 50], fn (): array => $this->splitter->split($this->route(3), -2));
        self::assertDomainError('outbound.pick_list_count_invalid', ['count' => 51, 'max' => 50], fn (): array => $this->splitter->split($this->route(3), 51));
        self::assertCount(3, $this->splitter->split($this->route(3), 50));
    }

    /**
     * @return list<PickRequest>
     */
    private function route(int $picks): array
    {
        return array_map(static fn (int $i): PickRequest => OutboundMother::pickRequest('A-01-'.\sprintf('%02d', $i), pickSequence: $i * 10), range(1, $picks));
    }
}
