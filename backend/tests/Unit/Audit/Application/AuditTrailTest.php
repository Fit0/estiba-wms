<?php

declare(strict_types=1);

namespace App\Tests\Unit\Audit\Application;

use App\Audit\Application\AuditTrail;
use App\Audit\Application\EventDescriber;
use App\Audit\Domain\AuditEntries;
use App\Audit\Domain\AuditEntry;
use App\Audit\Domain\AuditFilter;
use App\Outbound\Domain\Event\PickListStarted;
use App\Outbound\Domain\OperatorId;
use App\Outbound\Domain\PickListId;
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
use Psr\Log\AbstractLogger;

/**
 * Every event that is published is written down, with who did it (AUD-02).
 */
final class AuditTrailTest extends TestCase
{
    private InMemoryAuditEntries $entries;

    private RecordingLogger $logger;

    #[\Override]
    protected function setUp(): void
    {
        $this->entries = new InMemoryAuditEntries();
        $this->logger = new RecordingLogger();
    }

    public function testWritesWhatHappenedWhenItHappenedAndWhoDidIt(): void
    {
        $tenant = TenantId::fromString(Ids::next());
        $list = PickListId::fromString(Ids::next());
        $operator = OperatorId::fromString(Ids::next());
        $user = Ids::next();
        $event = new PickListStarted($list, $operator, $tenant, new \DateTimeImmutable('2026-10-24T08:00:00+00:00'));

        $this->trail(signedInTenant: $tenant, signedInUser: $user)->record($event);

        self::assertCount(1, $this->entries->added);
        $entry = $this->entries->added[0];
        self::assertSame('outbound.pick_list_started', $entry->eventType());
        self::assertSame($tenant->value, $entry->tenantId()->value);
        self::assertSame($user, $entry->actorId());
        self::assertSame($list->value, $entry->subjectId());
        self::assertEquals(new \DateTimeImmutable('2026-10-24T08:00:00+00:00'), $entry->occurredAt());
        self::assertEquals(new \DateTimeImmutable('2026-10-24T09:30:00+00:00'), $entry->recordedAt());
        self::assertSame(['pickListId' => $list->value, 'operatorId' => $operator->value, 'tenantId' => $tenant->value], $entry->data());
        self::assertSame([], $this->logger->warnings);
    }

    public function testNobodySignedInLeavesNoActor(): void
    {
        $tenant = TenantId::fromString(Ids::next());

        $this->trail(signedInTenant: null, signedInUser: null)->record($this->started($tenant));

        self::assertNull($this->entries->added[0]->actorId());
        self::assertSame($tenant->value, $this->entries->added[0]->tenantId()->value, 'The event says whose it is.');
    }

    public function testAnEventWithoutATenantTakesTheTenantOfTheSignedInUser(): void
    {
        $tenant = TenantId::fromString(Ids::next());
        $event = new class(PickListId::fromString(Ids::next())) implements DomainEvent {
            public function __construct(public PickListId $pickListId)
            {
            }

            public function occurredAt(): \DateTimeImmutable
            {
                return new \DateTimeImmutable('2026-10-24T08:00:00+00:00');
            }
        };

        $this->trail(signedInTenant: $tenant, signedInUser: Ids::next())->record($event);

        self::assertSame($tenant->value, $this->entries->added[0]->tenantId()->value);
    }

    public function testTheTenantOfTheEventWinsOverTheOneSignedIn(): void
    {
        $theirs = TenantId::fromString(Ids::next());

        $this->trail(signedInTenant: TenantId::fromString(Ids::next()), signedInUser: Ids::next())->record($this->started($theirs));

        self::assertSame($theirs->value, $this->entries->added[0]->tenantId()->value);
    }

    // The change that was made is valid whether or not it can be audited, so it is not undone
    public function testAnEventNobodyCanAttributeToATenantIsReportedAndNotWritten(): void
    {
        $event = new class implements DomainEvent {
            public function occurredAt(): \DateTimeImmutable
            {
                return new \DateTimeImmutable('2026-10-24T08:00:00+00:00');
            }
        };

        $this->trail(signedInTenant: null, signedInUser: null)->record($event);

        self::assertSame([], $this->entries->added);
        self::assertCount(1, $this->logger->warnings);
    }

    private function started(TenantId $tenant): PickListStarted
    {
        return new PickListStarted(PickListId::fromString(Ids::next()), OperatorId::fromString(Ids::next()), $tenant, new \DateTimeImmutable('2026-10-24T08:00:00+00:00'));
    }

    private function trail(?TenantId $signedInTenant, ?string $signedInUser): AuditTrail
    {
        return new AuditTrail(
            new EventDescriber(),
            $this->entries,
            new readonly class($signedInTenant) implements CurrentTenant {
                public function __construct(private ?TenantId $tenant)
                {
                }

                public function id(): TenantId
                {
                    return $this->tenant ?? throw TenantNotResolved::missing();
                }
            },
            new readonly class($signedInUser) implements CurrentUser {
                public function __construct(private ?string $user)
                {
                }

                public function id(): string
                {
                    return $this->user ?? throw TenantNotResolved::missing();
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
                    return new \DateTimeImmutable('2026-10-24T09:30:00+00:00');
                }
            },
            $this->logger,
        );
    }
}

final class InMemoryAuditEntries implements AuditEntries
{
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
}

final class RecordingLogger extends AbstractLogger
{
    /** @var list<string> */
    public array $warnings = [];

    public function log(mixed $level, string|\Stringable $message, array $context = []): void
    {
        if ('warning' === $level) {
            $this->warnings[] = (string) $message;
        }
    }
}
