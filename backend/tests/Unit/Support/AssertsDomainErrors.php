<?php

declare(strict_types=1);

namespace App\Tests\Unit\Support;

use App\Shared\Domain\DomainError;

/**
 * Domain tests assert the error code and its parameters, never the message,
 * so rewording or translating a message cannot break them (ADR 0004).
 */
trait AssertsDomainErrors
{
    /**
     * @param array<string, bool|float|int|string> $parameters
     */
    private static function assertDomainError(string $code, array $parameters, callable $action): void
    {
        try {
            $action();
        } catch (DomainError $error) {
            self::assertSame($code, $error->errorCode());
            self::assertSame($parameters, $error->errorParameters());

            return;
        }

        self::fail(\sprintf('Expected the domain error "%s", but nothing was thrown.', $code));
    }
}
