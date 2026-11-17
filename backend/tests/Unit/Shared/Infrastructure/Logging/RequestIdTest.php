<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Logging;

use App\Shared\Infrastructure\Logging\RequestId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RequestIdTest extends TestCase
{
    public function testIsTheSameForTheWholeRequest(): void
    {
        $id = new RequestId();

        self::assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $id->current());
        self::assertSame($id->current(), $id->current());
    }

    public function testKeepsTheIdentifierTheCallerSent(): void
    {
        $id = new RequestId();

        $id->adopt('billing-7f3a9c21');

        self::assertSame('billing-7f3a9c21', $id->current());
    }

    #[DataProvider('unacceptableIdentifiers')]
    public function testMakesAnotherOneWhenWhatTheCallerSentIsNotAcceptable(?string $received): void
    {
        $id = new RequestId();

        $id->adopt($received);

        self::assertNotSame($received, $id->current());
        self::assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $id->current());
    }

    /**
     * @return iterable<string, array{string|null}>
     */
    public static function unacceptableIdentifiers(): iterable
    {
        yield 'none was sent' => [null];
        yield 'too short' => ['abc'];
        yield 'too long' => [str_repeat('a', 65)];
        yield 'a space' => ['has a space in it'];
        yield 'a line break, which would write a line of its own' => ["abcdefgh\n{\"level\":\"critical\"}"];
    }

    public function testRenewingGivesAnotherIdentifierAndResettingForgetsIt(): void
    {
        $id = new RequestId();
        $first = $id->current();

        $id->renew();
        self::assertNotSame($first, $id->current());

        $id->reset();
        self::assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $id->current());
    }
}
