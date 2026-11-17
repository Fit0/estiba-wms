<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Logging;

use App\Shared\Application\CurrentTenant;
use App\Shared\Application\CurrentUser;
use App\Shared\Application\TenantNotResolved;
use Monolog\Attribute\AsMonologProcessor;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Adds to every log line the request it belongs to, and who and which tenant it acted for when somebody is
 * signed in (E10-04, ADR 0062). Only identifiers: an email address is personal data and never goes in a log.
 *
 * The two ports are lazy because the security layer logs too, and a logger that needs the security layer to
 * be built would wait for itself. A line written when nobody is signed in (the console, the worker, a request
 * that is still being authenticated) just has no user and no tenant.
 */
#[AsMonologProcessor]
final readonly class LogContextProcessor implements ProcessorInterface
{
    public function __construct(
        private RequestId $requestId,
        #[Autowire(lazy: true)]
        private CurrentUser $user,
        #[Autowire(lazy: true)]
        private CurrentTenant $tenant,
    ) {
    }

    #[\Override]
    public function __invoke(LogRecord $record): LogRecord
    {
        $extra = ['request_id' => $this->requestId->current()];

        $userId = $this->userId();
        if (null !== $userId) {
            $extra['user_id'] = $userId;
        }

        $tenantId = $this->tenantId();
        if (null !== $tenantId) {
            $extra['tenant_id'] = $tenantId;
        }

        return $record->with(extra: [...$record->extra, ...$extra]);
    }

    private function userId(): ?string
    {
        try {
            return $this->user->id();
        } catch (TenantNotResolved) {
            return null;
        }
    }

    private function tenantId(): ?string
    {
        try {
            return $this->tenant->id()->value;
        } catch (TenantNotResolved) {
            return null;
        }
    }
}
