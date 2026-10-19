<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use App\Shared\Application\CurrentTenant;
use App\Shared\Application\TenantNotResolved;
use App\Shared\Domain\InvalidIdentifier;
use App\Shared\Domain\TenantId;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Reads the tenant from the `X-Tenant-Id` header. This is a stand-in until authentication
 * (E02-06) takes the tenant from the JWT; only this class changes then.
 */
final readonly class HeaderCurrentTenant implements CurrentTenant
{
    public const string HEADER = 'X-Tenant-Id';

    public function __construct(private RequestStack $requests)
    {
    }

    #[\Override]
    public function id(): TenantId
    {
        $value = $this->requests->getCurrentRequest()?->headers->get(self::HEADER);

        if (null === $value) {
            throw TenantNotResolved::missing();
        }

        try {
            return TenantId::fromString($value);
        } catch (InvalidIdentifier) {
            throw TenantNotResolved::missing();
        }
    }
}
