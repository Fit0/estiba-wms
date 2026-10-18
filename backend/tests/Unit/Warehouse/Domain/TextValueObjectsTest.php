<?php

declare(strict_types=1);

namespace App\Tests\Unit\Warehouse\Domain;

use App\Tests\Unit\Support\AssertsDomainErrors;
use App\Warehouse\Domain\BlockReason;
use App\Warehouse\Domain\DisplayName;
use App\Warehouse\Domain\PickSequence;
use PHPUnit\Framework\TestCase;

/**
 * The free-text and number value objects, which have no invariant of their own beyond their format.
 */
final class TextValueObjectsTest extends TestCase
{
    use AssertsDomainErrors;

    public function testDisplayNameRemovesSurroundingSpaces(): void
    {
        self::assertSame('Madrid central', DisplayName::fromString('  Madrid central ')->value);
    }

    public function testDisplayNameCountsCharactersNotBytes(): void
    {
        $hundred = str_repeat('ñ', 100);

        self::assertSame($hundred, DisplayName::fromString($hundred)->value);
    }

    public function testDisplayNameRejectsEmptyTooLongAndControlCharacters(): void
    {
        foreach (['', '   ', str_repeat('a', 101), "Two\nlines", "Nul\x00byte"] as $value) {
            self::assertDomainError('warehouse.name_invalid', ['maxLength' => 100], static fn (): DisplayName => DisplayName::fromString($value));
        }
    }

    public function testBlockReasonRemovesSurroundingSpaces(): void
    {
        self::assertSame('Damaged rack', BlockReason::fromString(' Damaged rack ')->value);
    }

    public function testBlockReasonRejectsEmptyTooLongAndControlCharacters(): void
    {
        foreach (['', '   ', str_repeat('a', 201), "Two\nlines"] as $value) {
            self::assertDomainError('warehouse.block_reason_invalid', ['maxLength' => 200], static fn (): BlockReason => BlockReason::fromString($value));
        }
    }

    public function testBlockReasonAcceptsTheLongestAllowed(): void
    {
        $longest = str_repeat('a', 200);

        self::assertSame($longest, BlockReason::fromString($longest)->value);
    }

    public function testPickSequenceAcceptsZeroAndUp(): void
    {
        self::assertSame(0, PickSequence::of(0)->value);
        self::assertSame(250, PickSequence::of(250)->value);
    }

    public function testPickSequenceRejectsNegativeNumbers(): void
    {
        self::assertDomainError('warehouse.pick_sequence_invalid', ['value' => -1], static fn (): PickSequence => PickSequence::of(-1));
    }
}
