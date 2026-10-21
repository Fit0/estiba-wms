<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Messenger;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\DelayedMessageHandlingException;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\WrappedExceptionsInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * What the bus adapters share: dispatch a message and give back what its handler returned.
 *
 * Messenger wraps whatever a handler throws in a {@see HandlerFailedException}, and whatever an
 * event handler that ran after the command threw in a {@see DelayedMessageHandlingException}. The
 * application speaks in domain errors, and the API turns each of them into a translated problem,
 * so the original exception is thrown again.
 *
 * @internal
 */
final class HandledResult
{
    public static function of(MessageBusInterface $bus, object $message): mixed
    {
        try {
            $envelope = $bus->dispatch($message);
        } catch (HandlerFailedException|DelayedMessageHandlingException $failure) {
            throw self::originalOf($failure);
        }

        return self::returnedBy($envelope);
    }

    /**
     * The first exception a handler threw, unwrapped from Messenger's wrappers.
     */
    public static function originalOf(\Throwable $failure): \Throwable
    {
        while ($failure instanceof WrappedExceptionsInterface) {
            $first = array_values($failure->getWrappedExceptions())[0] ?? null;
            if (!$first instanceof \Throwable) {
                return $failure;
            }

            $failure = $first;
        }

        return $failure;
    }

    private static function returnedBy(Envelope $envelope): mixed
    {
        $stamp = $envelope->last(HandledStamp::class);

        return $stamp instanceof HandledStamp ? $stamp->getResult() : null;
    }
}
