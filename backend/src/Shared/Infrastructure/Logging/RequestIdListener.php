<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Logging;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;

/**
 * Gives every request and every message of the worker its identifier (E10-04, ADR 0062), and returns it in
 * the `X-Request-Id` header, so whoever reports an error can say which request it was.
 */
final readonly class RequestIdListener
{
    public const string HEADER = 'X-Request-Id';

    public function __construct(private RequestId $requestId)
    {
    }

    /** Before the firewall and the router, so what they log already carries it. */
    #[AsEventListener(event: KernelEvents::REQUEST, priority: 512)]
    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $this->requestId->adopt($event->getRequest()->headers->get(self::HEADER));
    }

    #[AsEventListener(event: KernelEvents::RESPONSE)]
    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $event->getResponse()->headers->set(self::HEADER, $this->requestId->current());
    }

    /** A worker lives for hours: each message is its own story. */
    #[AsEventListener(event: WorkerMessageReceivedEvent::class)]
    public function onMessage(): void
    {
        $this->requestId->renew();
    }
}
