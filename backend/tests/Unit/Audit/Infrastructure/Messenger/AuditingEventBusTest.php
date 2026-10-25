<?php

declare(strict_types=1);

namespace App\Tests\Unit\Audit\Infrastructure\Messenger;

use App\Audit\Application\AuditTrail;
use App\Audit\Application\EventDescriber;
use App\Audit\Domain\AuditEntries;
use App\Audit\Domain\AuditEntry;
use App\Audit\Domain\AuditFilter;
use App\Audit\Infrastructure\Messenger\AuditingEventBus;
use App\Outbound\Domain\Event\PickListStarted;
use App\Outbound\Domain\Event\WaveCreated;
use App\Outbound\Domain\OperatorId;
use App\Outbound\Domain\PickListId;
use App\Outbound\Domain\WaveId;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Application\Clock;
use App\Shared\Application\CurrentTenant;
use App\Shared\Application\CurrentUser;
use App\Shared\Application\IdGenerator;
use App\Shared\Application\TenantNotResolved;
use App\Shared\Domain\DomainEvent;
use App\Shared\Domain\Page;
use App\Shared\Domain\PageRequest;
use App\Shared\Domain\TenantId;
use App\Tests\Unit\Support\Ids;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * The event bus that writes the audit trail still does what the event bus does (AUD-02).
 */
final class AuditingEventBusTest extends TestCase
{
    public function testEveryEventIsWrittenDownAndThenPassedOn(): void
    {
        $audit = new class implements AuditEntries {
            /** @var list<AuditEntry> */
            public array $added = [];

            public function add(AuditEntry $entry): void
            {
                $this->added[] = $entry;
            }

            public function search(TenantId $tenantId, AuditFilter $filter, PageRequest $page): Page
            {
                return new Page($this->added, \count($this->added), $page);
            }
        };
        $inner = new class($audit) implements EventBus {
            /** @var list<DomainEvent> */
            public array $published = [];

            /** @var list<int> how many lines the trail had when the events came */
            public array $linesWhenPublished = [];

            public function __construct(private readonly AuditEntries $audit)
            {
            }

            public function publish(DomainEvent ...$events): void
            {
                $this->linesWhenPublished[] = \count($this->audit->search(TenantId::fromString(Ids::next()), new AuditFilter(), PageRequest::of())->items);
                array_push($this->published, ...$events);
            }
        };
        $tenant = TenantId::fromString(Ids::next());
        $started = new PickListStarted(PickListId::fromString(Ids::next()), OperatorId::fromString(Ids::next()), $tenant, new \DateTimeImmutable('2026-10-25T08:00:00+00:00'));
        $created = new WaveCreated(WaveId::fromString(Ids::next()), $tenant, new \DateTimeImmutable('2026-10-25T08:01:00+00:00'));

        $this->bus($inner, $audit)->publish($started, $created);

        self::assertSame([$started, $created], $inner->published, 'The events go on to the bus, in order.');
        self::assertSame(['outbound.pick_list_started', 'outbound.wave_created'], array_map(static fn (AuditEntry $entry): string => $entry->eventType(), $audit->added));
        self::assertSame([2], $inner->linesWhenPublished, 'They were written before the policies could run.');
    }

    public function testNothingPublishedWritesNothing(): void
    {
        $audit = new class implements AuditEntries {
            public int $count = 0;

            public function add(AuditEntry $entry): void
            {
                ++$this->count;
            }

            public function search(TenantId $tenantId, AuditFilter $filter, PageRequest $page): Page
            {
                return new Page([], 0, $page);
            }
        };

        $this->bus(new class implements EventBus {
            public function publish(DomainEvent ...$events): void
            {
            }
        }, $audit)->publish();

        self::assertSame(0, $audit->count);
    }

    public function testWhatTheBusRefusesReachesWhoPublished(): void
    {
        $failing = new class implements EventBus {
            public function publish(DomainEvent ...$events): void
            {
                throw new \RuntimeException('A policy failed.');
            }
        };
        $audit = new class implements AuditEntries {
            public function add(AuditEntry $entry): void
            {
            }

            public function search(TenantId $tenantId, AuditFilter $filter, PageRequest $page): Page
            {
                return new Page([], 0, $page);
            }
        };
        $created = new WaveCreated(WaveId::fromString(Ids::next()), TenantId::fromString(Ids::next()), new \DateTimeImmutable('2026-10-25T08:01:00+00:00'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('A policy failed.');

        $this->bus($failing, $audit)->publish($created);
    }

    private function bus(EventBus $inner, AuditEntries $audit): AuditingEventBus
    {
        $trail = new AuditTrail(
            new EventDescriber(),
            $audit,
            new class implements CurrentTenant {
                public function id(): TenantId
                {
                    throw TenantNotResolved::missing();
                }
            },
            new class implements CurrentUser {
                public function id(): string
                {
                    throw TenantNotResolved::missing();
                }
            },
            new class implements IdGenerator {
                public function next(): string
                {
                    return Ids::next();
                }
            },
            new class implements Clock {
                public function now(): \DateTimeImmutable
                {
                    return new \DateTimeImmutable('2026-10-25T09:00:00+00:00');
                }
            },
            new NullLogger(),
        );

        return new AuditingEventBus($inner, $trail);
    }
}
