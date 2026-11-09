<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure;

use App\Shared\Application\CurrentTenant;
use App\Shared\Application\TenantNotResolved;
use App\Shared\Domain\TenantId;
use App\Shared\Infrastructure\Tenancy\ScopedCurrentTenant;
use App\Tests\Unit\Support\Ids;
use PHPUnit\Framework\TestCase;

final class ScopedCurrentTenantTest extends TestCase
{
    public function testWithoutAJobItIsTheTenantOfTheUser(): void
    {
        $user = TenantId::fromString(Ids::next());

        self::assertSame($user, new ScopedCurrentTenant($this->tenantOf($user))->id());
    }

    public function testWithoutAJobAndWithoutAUserThereIsNoTenant(): void
    {
        $this->expectException(TenantNotResolved::class);

        new ScopedCurrentTenant($this->tenantOf(null))->id();
    }

    public function testAJobActsForItsOwnTenantAndGivesBackWhatTheWorkReturns(): void
    {
        $job = TenantId::fromString(Ids::next());
        $tenants = new ScopedCurrentTenant($this->tenantOf(TenantId::fromString(Ids::next())));

        $seen = $tenants->run($job, static fn (): TenantId => $tenants->id());

        self::assertSame($job, $seen);
    }

    public function testTheTenantOfTheJobEndsWithTheJobEvenWhenItFails(): void
    {
        $user = TenantId::fromString(Ids::next());
        $tenants = new ScopedCurrentTenant($this->tenantOf($user));

        $this->expectException(\LogicException::class);

        try {
            $tenants->run(TenantId::fromString(Ids::next()), static fn (): never => throw new \LogicException('The job failed.'));
        } finally {
            self::assertSame($user, $tenants->id());
        }
    }

    public function testAJobInsideAJobRestoresTheOuterOne(): void
    {
        $outer = TenantId::fromString(Ids::next());
        $inner = TenantId::fromString(Ids::next());
        $tenants = new ScopedCurrentTenant($this->tenantOf(null));

        $seen = $tenants->run($outer, static function () use ($tenants, $inner): array {
            $inside = $tenants->run($inner, static fn (): TenantId => $tenants->id());

            return [$inside, $tenants->id()];
        });

        self::assertSame([$inner, $outer], $seen);
    }

    private function tenantOf(?TenantId $tenant): CurrentTenant
    {
        return new readonly class($tenant) implements CurrentTenant {
            public function __construct(private ?TenantId $tenant)
            {
            }

            #[\Override]
            public function id(): TenantId
            {
                return $this->tenant ?? throw TenantNotResolved::missing();
            }
        };
    }
}
