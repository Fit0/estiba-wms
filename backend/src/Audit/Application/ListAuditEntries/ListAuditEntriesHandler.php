<?php

declare(strict_types=1);

namespace App\Audit\Application\ListAuditEntries;

use App\Audit\Domain\AuditEntries;
use App\Identity\Application\Contract\UserDirectory;
use App\Shared\Application\Bus\QueryHandler;
use App\Shared\Application\CurrentTenant;
use App\Shared\Domain\Page;

final readonly class ListAuditEntriesHandler implements QueryHandler
{
    public function __construct(
        private AuditEntries $entries,
        private UserDirectory $users,
        private CurrentTenant $tenant,
    ) {
    }

    /**
     * @return Page<AuditLine>
     */
    public function __invoke(ListAuditEntries $query): Page
    {
        $page = $this->entries->search($this->tenant->id(), $query->filter, $query->page);

        $actorIds = [];
        foreach ($page->items as $entry) {
            if (null !== $entry->actorId()) {
                $actorIds[$entry->actorId()] = $entry->actorId();
            }
        }
        $emails = $this->users->emailsOf($this->tenant->id(), array_values($actorIds));

        return new Page(
            array_map(static fn ($entry): AuditLine => new AuditLine($entry, null === $entry->actorId() ? null : ($emails[$entry->actorId()] ?? null)), $page->items),
            $page->total,
            $page->request,
        );
    }
}
