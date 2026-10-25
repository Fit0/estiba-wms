<?php

declare(strict_types=1);

namespace App\Tests\Unit\Audit;

use App\Catalog\Domain\Event\GtinAssigned;
use App\Catalog\Domain\Event\PackagingLevelDefined;
use App\Catalog\Domain\Event\ProductBlocked;
use App\Catalog\Domain\Event\ProductClassified;
use App\Catalog\Domain\Event\ProductDiscontinued;
use App\Catalog\Domain\Event\ProductUnblocked;
use App\Identity\Domain\Event\UserDisabled;
use App\Identity\Domain\Event\UserEnabled;
use App\Inbound\Domain\Event\AsnCancelled;
use App\Inbound\Domain\Event\AsnClosed;
use App\Inbound\Domain\Event\AsnRegistered;
use App\Inbound\Domain\Event\DiscrepancyDetected;
use App\Inbound\Domain\Event\ItemReceived;
use App\Inbound\Domain\Event\OverReceiptApproved;
use App\Inbound\Domain\Event\PutawayTaskSplit;
use App\Inbound\Domain\Event\ReceiptClosed;
use App\Inbound\Domain\Event\ReceiptOpened;
use App\Shared\Domain\DomainEvent;
use App\Shared\Domain\TenantId;
use App\Warehouse\Domain\Event\LocationBlocked;
use App\Warehouse\Domain\Event\LocationCreated;
use App\Warehouse\Domain\Event\LocationUnblocked;
use App\Warehouse\Domain\Event\ZoneDefined;
use App\Warehouse\Domain\Event\ZoneFamiliesAssigned;
use PHPUnit\Framework\TestCase;

/**
 * An event tells its tenant, so the audit trail can write it down wherever it is published (ADR 0018).
 *
 * The trail takes the tenant of the signed-in user when the event has none, and that works for an event
 * published by a request. A policy that runs in a worker has nobody signed in, so an event it publishes
 * without a tenant would be lost to the trail, with only a warning in the log.
 *
 * The events below were written before this rule and are published only by requests. The list can only
 * shrink: a new event carries its tenant, and a new entry here is a decision to be made on purpose.
 */
final class EventsCarryTheirTenantTest extends TestCase
{
    /** Published only by use cases that a signed-in user starts. */
    private const array WITHOUT_TENANT = [
        GtinAssigned::class,
        PackagingLevelDefined::class,
        ProductBlocked::class,
        ProductClassified::class,
        ProductDiscontinued::class,
        ProductUnblocked::class,
        UserDisabled::class,
        UserEnabled::class,
        AsnCancelled::class,
        AsnClosed::class,
        AsnRegistered::class,
        DiscrepancyDetected::class,
        ItemReceived::class,
        OverReceiptApproved::class,
        PutawayTaskSplit::class,
        ReceiptClosed::class,
        ReceiptOpened::class,
        LocationBlocked::class,
        LocationCreated::class,
        LocationUnblocked::class,
        ZoneDefined::class,
        ZoneFamiliesAssigned::class,
    ];

    public function testANewEventCarriesItsTenant(): void
    {
        $without = $this->eventsWithoutTenant();

        self::assertSame([], array_values(array_diff($without, self::WITHOUT_TENANT)), 'These events carry no tenant. Give them a `public TenantId $tenantId`.');
    }

    public function testTheListOfEventsWithoutTenantOnlyShrinks(): void
    {
        $without = $this->eventsWithoutTenant();

        self::assertSame([], array_values(array_diff(self::WITHOUT_TENANT, $without)), 'These events carry their tenant now: take them out of the list.');
    }

    public function testThereAreEventsToLookAt(): void
    {
        self::assertGreaterThan(40, \count($this->events()));
    }

    /**
     * @return list<class-string<DomainEvent>>
     */
    private function eventsWithoutTenant(): array
    {
        $without = [];
        foreach ($this->events() as $class) {
            $constructor = new \ReflectionClass($class)->getConstructor();
            $carries = false;
            foreach ($constructor?->getParameters() ?? [] as $parameter) {
                $type = $parameter->getType();
                if ('tenantId' === $parameter->getName() && $type instanceof \ReflectionNamedType && TenantId::class === $type->getName()) {
                    $carries = true;
                }
            }
            if (!$carries) {
                $without[] = $class;
            }
        }

        sort($without);

        return $without;
    }

    /**
     * @return list<class-string<DomainEvent>>
     */
    private function events(): array
    {
        $events = [];
        $files = glob(\dirname(__DIR__, 3).'/src/*/Domain/Event/*.php');
        foreach (false === $files ? [] : $files as $file) {
            $context = basename(\dirname($file, 3));
            $class = \sprintf('App\%s\Domain\Event\%s', $context, basename($file, '.php'));
            if (is_subclass_of($class, DomainEvent::class)) {
                $events[] = $class;
            }
        }

        return $events;
    }
}
