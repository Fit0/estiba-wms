<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Logging;

use Symfony\Contracts\Service\ResetInterface;

/**
 * The identifier that ties together every log line of one request, or of one message that the worker
 * handles (E10-04, ADR 0062). A caller that sends `X-Request-Id` keeps its own, so a line of the API can be
 * found from the system that called it, as long as the value is short and made of plain characters: it
 * ends up in a log, and a value with a line break in it would write a line of its own.
 *
 * It holds state for the length of one request, and `reset()` clears it.
 */
final class RequestId implements ResetInterface
{
    private const string ACCEPTED = '/^[A-Za-z0-9._-]{8,64}$/';

    private ?string $current = null;

    public function current(): string
    {
        $this->current ??= $this->generate();

        return $this->current;
    }

    /**
     * Takes the identifier the caller sent, or makes a new one when it sent none or sent one that is not
     * accepted.
     */
    public function adopt(?string $received): void
    {
        $this->current = null !== $received && 1 === preg_match(self::ACCEPTED, $received)
            ? $received
            : $this->generate();
    }

    public function renew(): void
    {
        $this->current = $this->generate();
    }

    #[\Override]
    public function reset(): void
    {
        $this->current = null;
    }

    private function generate(): string
    {
        return bin2hex(random_bytes(8));
    }
}
