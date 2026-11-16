<?php

declare(strict_types=1);

namespace App\Tests\Unit\Audit\Application;

use App\Audit\Application\ListAuditEntries\ListAuditEntries;
use App\Audit\Application\ListAuditEntries\ListAuditEntriesHandler;
use App\Audit\Domain\AuditEntries;
use App\Audit\Domain\AuditEntry;
use App\Audit\Domain\AuditEntryId;
use App\Audit\Domain\AuditFilter;
use App\Identity\Application\Contract\UserDirectory;
use App\Shared\Application\CurrentTenant;
use App\Shared\Domain\Page;
use App\Shared\Domain\PageRequest;
use App\Shared\Domain\TenantId;
use App\Tests\Unit\Support\Ids;
use PHPUnit\Framework\TestCase;

/**
 * The trail says whose an entry is by the email the user has now (AUD-02), and never fails for a user
 * that is gone.
 */
final class ListAuditEntriesHandlerTest extends TestCase
{
    public function testNamesTheActorOfEachEntryAndLeavesOutWhoIsNotKnown(): void
    {
        $tenant = TenantId::fromString(Ids::next());
        $ana = Ids::next();
        $gone = Ids::next();
        $entries = [
            $this->entry($tenant, $ana),
            $this->entry($tenant, $gone),
            $this->entry($tenant, null),
        ];

        $page = $this->handler($tenant, $entries, [$ana => 'ana@marea-moda.example'])(new ListAuditEntries(PageRequest::of()));

        self::assertSame(['ana@marea-moda.example', null, null], array_map(static fn ($line): ?string => $line->actorEmail, $page->items));
        self::assertSame($entries, array_map(static fn ($line): AuditEntry => $line->entry, $page->items));
        self::assertSame(3, $page->total);
    }

    public function testAsksForEachUserOnceAndOnlyForTheTenantOfTheRequest(): void
    {
        $tenant = TenantId::fromString(Ids::next());
        $ana = Ids::next();
        $directory = new class implements UserDirectory {
            public ?TenantId $tenant = null;

            /** @var list<string> */
            public array $asked = [];

            #[\Override]
            public function emailsOf(TenantId $tenantId, array $userIds): array
            {
                $this->tenant = $tenantId;
                $this->asked = $userIds;

                return [];
            }
        };

        $handler = new ListAuditEntriesHandler($this->entries([$this->entry($tenant, $ana), $this->entry($tenant, $ana), $this->entry($tenant, null)]), $directory, $this->currentTenant($tenant));
        $handler(new ListAuditEntries(PageRequest::of()));

        self::assertSame($tenant, $directory->tenant);
        self::assertSame([$ana], $directory->asked);
    }

    private function entry(TenantId $tenant, ?string $actorId): AuditEntry
    {
        $at = new \DateTimeImmutable('2026-11-10T10:00:00+00:00');

        return new AuditEntry(AuditEntryId::fromString(Ids::next()), $tenant, 'outbound.order_registered', $at, $at, $actorId, Ids::next(), []);
    }

    /**
     * @param list<AuditEntry>      $entries
     * @param array<string, string> $emails
     */
    private function handler(TenantId $tenant, array $entries, array $emails): ListAuditEntriesHandler
    {
        $directory = new readonly class($emails) implements UserDirectory {
            /** @param array<string, string> $emails */
            public function __construct(private array $emails)
            {
            }

            #[\Override]
            public function emailsOf(TenantId $tenantId, array $userIds): array
            {
                return array_intersect_key($this->emails, array_flip($userIds));
            }
        };

        return new ListAuditEntriesHandler($this->entries($entries), $directory, $this->currentTenant($tenant));
    }

    /**
     * @param list<AuditEntry> $entries
     */
    private function entries(array $entries): AuditEntries
    {
        return new readonly class($entries) implements AuditEntries {
            /** @param list<AuditEntry> $entries */
            public function __construct(private array $entries)
            {
            }

            #[\Override]
            public function add(AuditEntry $entry): void
            {
            }

            #[\Override]
            public function search(TenantId $tenantId, AuditFilter $filter, PageRequest $page): Page
            {
                return new Page($this->entries, \count($this->entries), $page);
            }
        };
    }

    private function currentTenant(TenantId $tenant): CurrentTenant
    {
        return new readonly class($tenant) implements CurrentTenant {
            public function __construct(private TenantId $tenant)
            {
            }

            #[\Override]
            public function id(): TenantId
            {
                return $this->tenant;
            }
        };
    }
}
