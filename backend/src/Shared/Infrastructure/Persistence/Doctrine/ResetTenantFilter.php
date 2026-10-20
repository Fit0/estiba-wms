<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Starts every request with the {@see TenantFilter} off, before the firewall runs (priority 8).
 *
 * The firewall loads the user from the database. If a process served a request for one tenant and
 * then another, the filter of the first would still be on, and it would hide the user of the
 * second. {@see EnableTenantFilter} switches the filter on again, once the user is known.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 9)]
final readonly class ResetTenantFilter
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $filters = $this->entityManager->getFilters();
        if ($filters->isEnabled(TenantFilter::NAME)) {
            $filters->disable(TenantFilter::NAME);
        }
    }
}
