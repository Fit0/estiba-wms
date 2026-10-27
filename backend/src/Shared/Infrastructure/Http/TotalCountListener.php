<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use ApiPlatform\State\Pagination\PaginatorInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Tells a client how many items a paged list has in all its pages, in the `X-Total-Count` header.
 *
 * The lists are sent as a plain JSON array, so the answer alone cannot say whether there is a next
 * page, or how many pages there are: a table needs that to draw its paging bar. The number comes
 * from the paginator the state provider built, so it is the total of the query with its filters,
 * and not the size of the page.
 */
#[AsEventListener(event: KernelEvents::RESPONSE)]
final readonly class TotalCountListener
{
    public const string HEADER = 'X-Total-Count';

    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest() || Response::HTTP_OK !== $event->getResponse()->getStatusCode()) {
            return;
        }

        $attributes = $event->getRequest()->attributes;
        $data = $attributes->get('data') ?? $attributes->get('read_data');
        if (!$data instanceof PaginatorInterface) {
            return;
        }

        $event->getResponse()->headers->set(self::HEADER, (string) (int) $data->getTotalItems());
    }
}
