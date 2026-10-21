<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inbound\Domain\Putaway;

use App\Inbound\Domain\LocationId;
use App\Inbound\Domain\ProductId;
use App\Inbound\Domain\Putaway\ByFamily;
use App\Inbound\Domain\Putaway\ByTurnover;
use App\Inbound\Domain\Putaway\FirstFreeLocation;
use App\Inbound\Domain\Putaway\PutawayCandidate;
use App\Inbound\Domain\Putaway\PutawayProduct;
use App\Inbound\Domain\Putaway\PutawayStrategyName;
use App\Shared\Domain\ProductFamily;
use App\Shared\Domain\TurnoverClass;
use App\Tests\Unit\Support\Ids;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PutawayStrategiesTest extends TestCase
{
    public function testFirstFreeTakesTheFirstEmptyLocationWhereTheUnitsFit(): void
    {
        $candidates = [
            $this->candidate('A-01-01', empty: false),
            $this->candidate('A-01-02', hasRoom: false),
            $this->candidate('A-01-03'),
            $this->candidate('A-01-04'),
        ];

        self::assertSame('A-01-03', new FirstFreeLocation()->choose($this->product(), $candidates)?->code);
        self::assertNull(new FirstFreeLocation()->choose($this->product(), [$this->candidate('A-01-01', empty: false)]));
        self::assertNull(new FirstFreeLocation()->choose($this->product(), []));
    }

    public function testByFamilyKeepsAFamilyTogether(): void
    {
        $candidates = [
            $this->candidate('A-01-01'),
            $this->candidate('B-01-01', families: ['CALZADO']),
            $this->candidate('C-01-01', families: ['CAMISERIA', 'PUNTO'], empty: false),
            $this->candidate('C-01-02', families: ['CAMISERIA', 'PUNTO']),
        ];

        self::assertSame('C-01-02', new ByFamily()->choose($this->product('camiseria'), $candidates)?->code);
        self::assertSame('B-01-01', new ByFamily()->choose($this->product('calzado'), $candidates)?->code);
    }

    public function testByFamilyFallsBackToTheFirstFreeLocation(): void
    {
        $candidates = [
            $this->candidate('A-01-01', empty: false),
            $this->candidate('A-01-02'),
            $this->candidate('C-01-01', families: ['CAMISERIA'], empty: false),
        ];

        self::assertSame('A-01-02', new ByFamily()->choose($this->product('camiseria'), $candidates)?->code, 'The family zone is full.');
        self::assertSame('A-01-02', new ByFamily()->choose($this->product(), $candidates)?->code, 'The product has no family.');
    }

    /**
     * @return iterable<string, array{TurnoverClass, string}>
     */
    public static function turnover(): iterable
    {
        yield 'a fast mover takes the first' => [TurnoverClass::A, 'A-01-02'];
        yield 'an average one takes the middle' => [TurnoverClass::B, 'A-01-04'];
        yield 'a slow mover takes the last' => [TurnoverClass::C, 'A-01-06'];
    }

    #[DataProvider('turnover')]
    public function testByTurnoverKeepsTheFastMoversClose(TurnoverClass $class, string $expected): void
    {
        $candidates = [
            $this->candidate('A-01-01', empty: false),
            $this->candidate('A-01-02'),
            $this->candidate('A-01-03'),
            $this->candidate('A-01-04'),
            $this->candidate('A-01-05'),
            $this->candidate('A-01-06'),
            $this->candidate('A-01-07', hasRoom: false),
        ];

        self::assertSame($expected, new ByTurnover()->choose($this->product(null, $class), $candidates)?->code);
    }

    public function testByTurnoverFindsNothingWhenNothingIsFree(): void
    {
        self::assertNull(new ByTurnover()->choose($this->product(), [$this->candidate('A-01-01', empty: false)]));
    }

    public function testEveryStrategyCanBeChosenByName(): void
    {
        self::assertInstanceOf(FirstFreeLocation::class, PutawayStrategyName::FirstFree->strategy());
        self::assertInstanceOf(ByFamily::class, PutawayStrategyName::ByFamily->strategy());
        self::assertInstanceOf(ByTurnover::class, PutawayStrategyName::ByTurnover->strategy());
    }

    private function product(?string $family = null, TurnoverClass $turnoverClass = TurnoverClass::B): PutawayProduct
    {
        return new PutawayProduct(ProductId::fromString(Ids::next()), null === $family ? null : ProductFamily::fromString($family), $turnoverClass);
    }

    /**
     * @param list<string> $families
     */
    private function candidate(string $code, array $families = [], bool $empty = true, bool $hasRoom = true): PutawayCandidate
    {
        return new PutawayCandidate(LocationId::fromString(Ids::next()), $code, array_map(ProductFamily::fromString(...), $families), $empty, $hasRoom);
    }
}
