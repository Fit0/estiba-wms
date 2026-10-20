<?php

declare(strict_types=1);

namespace App\Tests\Functional\Shared\Persistence;

use App\Shared\Application\CurrentTenant;
use App\Shared\Application\TenantNotResolved;
use App\Shared\Domain\TenantId;
use App\Shared\Infrastructure\Persistence\Doctrine\EnableTenantFilter;
use App\Shared\Infrastructure\Persistence\Doctrine\ResetTenantFilter;
use App\Shared\Infrastructure\Persistence\Doctrine\TenantFilter;
use App\Tests\Functional\Warehouse\Persistence\PersistenceTestCase;
use App\Tests\Unit\Support\Ids;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * The filter is only a wall if it is switched on for every signed-in request and off for the rest.
 */
final class EnableTenantFilterTest extends PersistenceTestCase
{
    public function testSwitchesTheFilterOnForASignedInUser(): void
    {
        $tenant = TenantId::fromString(Ids::next());

        $this->listenerFor($tenant)($this->event());

        $filters = $this->entityManager->getFilters();
        self::assertTrue($filters->isEnabled(TenantFilter::NAME));
        self::assertStringContainsString($tenant->value, $filters->getFilter(TenantFilter::NAME)->getParameter(TenantFilter::PARAMETER));
    }

    public function testLeavesTheFilterOffWhenNobodyIsSignedIn(): void
    {
        $this->listenerFor(null)($this->event());

        self::assertFalse($this->entityManager->getFilters()->isEnabled(TenantFilter::NAME));
    }

    // If one process serves several requests, a request must not inherit the tenant of the previous one.
    public function testTheFilterIsSwitchedOffAtTheStartOfEveryRequest(): void
    {
        $this->listenerFor(TenantId::fromString(Ids::next()))($this->event());
        self::assertTrue($this->entityManager->getFilters()->isEnabled(TenantFilter::NAME));

        new ResetTenantFilter($this->entityManager)($this->event());

        self::assertFalse($this->entityManager->getFilters()->isEnabled(TenantFilter::NAME));
    }

    public function testTheResetIgnoresSubRequests(): void
    {
        $this->listenerFor(TenantId::fromString(Ids::next()))($this->event());

        new ResetTenantFilter($this->entityManager)($this->event(HttpKernelInterface::SUB_REQUEST));

        self::assertTrue($this->entityManager->getFilters()->isEnabled(TenantFilter::NAME));
    }

    public function testFollowsTheTenantOfTheLatestRequest(): void
    {
        $first = TenantId::fromString(Ids::next());
        $second = TenantId::fromString(Ids::next());

        $this->listenerFor($first)($this->event());
        $this->listenerFor($second)($this->event());

        $parameter = $this->entityManager->getFilters()->getFilter(TenantFilter::NAME)->getParameter(TenantFilter::PARAMETER);
        self::assertStringContainsString($second->value, $parameter);
        self::assertStringNotContainsString($first->value, $parameter);
    }

    public function testIgnoresSubRequests(): void
    {
        $this->listenerFor(TenantId::fromString(Ids::next()))($this->event(HttpKernelInterface::SUB_REQUEST));

        self::assertFalse($this->entityManager->getFilters()->isEnabled(TenantFilter::NAME));
    }

    // Both listeners must really be registered: the reset before the firewall (priority 8) loads the
    // user, and the enabling right after it has signed the user in.
    public function testTheListenersAreRegisteredAroundTheFirewall(): void
    {
        $dispatcher = self::getContainer()->get('event_dispatcher');

        $priorities = ['reset' => [], 'enable' => []];
        foreach ($dispatcher->getListeners(KernelEvents::REQUEST) as $listener) {
            $priority = \is_callable($listener) ? $dispatcher->getListenerPriority(KernelEvents::REQUEST, $listener) : null;
            if (\is_array($listener) && $listener[0] instanceof ResetTenantFilter) {
                $priorities['reset'][] = $priority;
            }
            if (\is_array($listener) && $listener[0] instanceof EnableTenantFilter) {
                $priorities['enable'][] = $priority;
            }
        }

        self::assertSame(['reset' => [9], 'enable' => [7]], $priorities);
    }

    private function listenerFor(?TenantId $tenant): EnableTenantFilter
    {
        return new EnableTenantFilter($this->entityManager, new readonly class($tenant) implements CurrentTenant {
            public function __construct(private ?TenantId $tenant)
            {
            }

            public function id(): TenantId
            {
                return $this->tenant ?? throw TenantNotResolved::missing();
            }
        });
    }

    private function event(int $type = HttpKernelInterface::MAIN_REQUEST): RequestEvent
    {
        return new RequestEvent(self::$kernel ?? throw new \LogicException('The kernel is not booted.'), new Request(), $type);
    }
}
