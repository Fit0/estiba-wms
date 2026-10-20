<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Messenger;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * What both bus adapters share: dispatch a message and give back what its handler returned.
 *
 * Messenger wraps whatever a handler throws in a {@see HandlerFailedException}. The application
 * speaks in domain errors, and the API turns each of them into a translated problem, so the
 * original exception is thrown again.
 *
 * @internal
 */
final class HandledResult
{
    public static function of(MessageBusInterface $bus, object $message): mixed
    {
        try {
            $envelope = $bus->dispatch($message);
        } catch (HandlerFailedException $failure) {
            // The exceptions are keyed by the name of the handler that threw them. There is one handler per message.
            throw array_values($failure->getWrappedExceptions())[0] ?? $failure;
        }

        return self::returnedBy($envelope);
    }

    private static function returnedBy(Envelope $envelope): mixed
    {
        $stamp = $envelope->last(HandledStamp::class);

        return $stamp instanceof HandledStamp ? $stamp->getResult() : null;
    }
}
