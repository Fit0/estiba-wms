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
 * {@see ResetTenantFilter} has already switched the filter off at the start of the request, so an
 * anonymous request never carries the tenant of the previous one.
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

        try {
            $tenantId = $this->tenant->id();
        } catch (TenantNotResolved) {
            return;
        }

        $filter = $this->entityManager->getFilters()->enable(TenantFilter::NAME);
        $filter->setParameter(TenantFilter::PARAMETER, $tenantId, 'tenant_id');
    }
}
