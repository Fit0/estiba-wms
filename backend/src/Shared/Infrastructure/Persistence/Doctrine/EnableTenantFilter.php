<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\Doctrine;

use App\Shared\Application\CurrentTenant;
use App\Shared\Application\TenantNotResolved;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Turns the {@see TenantFilter} on for a signed-in user. It runs just after the firewall (priority 8)
 * has authenticated the request.
 *
 * Every request starts from a known state: the filter is switched off first, and on again only
 * for a user. That keeps one request from inheriting the tenant of the previous one if the process
 * ever serves several requests.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 7)]
final readonly class EnableTenantFilter
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CurrentTenant $tenant,
    ) {
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

        try {
            $tenantId = $this->tenant->id();
        } catch (TenantNotResolved) {
            return;
        }

        $filter = $filters->enable(TenantFilter::NAME);
        $filter->setParameter(TenantFilter::PARAMETER, $tenantId, 'tenant_id');
    }
}
