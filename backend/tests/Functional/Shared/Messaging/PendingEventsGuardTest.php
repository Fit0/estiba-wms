<?php

declare(strict_types=1);

namespace App\Tests\Functional\Shared\Messaging;

use App\Identity\Domain\Tenant;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\EventBus;
use App\Tests\Functional\Shared\Messaging\Fixtures\ForgetfulTrigger;
use App\Tests\Functional\Shared\Messaging\Fixtures\RaiseEvents;
use App\Tests\Functional\Warehouse\Persistence\PersistenceTestCase;
use App\Tests\Unit\Support\Ids;

/**
 * An event that an aggregate raises and nobody publishes is lost to the policies and to the audit
 * trail, and nothing else would notice (ADR 0018). In development and in the tests the command bus
 * checks it.
 */
final class PendingEventsGuardTest extends PersistenceTestCase
{
    private CommandBus $commandBus;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->commandBus = self::getContainer()->get(CommandBus::class);
    }

    public function testACommandThatPublishesWhatItsAggregatesRaisedPasses(): void
    {
        $tenantId = Ids::next();

        self::assertTrue($this->commandBus->dispatch(new RaiseEvents($tenantId, publish: true)));

        self::assertSame(1, $this->tenantsWithId($tenantId));
    }

    public function testACommandThatLeavesEventsBehindFailsAndNamesThem(): void
    {
        try {
            $this->commandBus->dispatch(new RaiseEvents(Ids::next(), publish: false));
            self::fail('A command that forgets its events should fail.');
        } catch (\LogicException $error) {
            self::assertStringContainsString(RaiseEvents::class, $error->getMessage());
            self::assertStringContainsString(Tenant::class, $error->getMessage());
            self::assertStringContainsString('TenantRegistered', $error->getMessage());
            self::assertStringContainsString('ADR 0018', $error->getMessage());
        }
    }

    public function testWhatTheForgetfulCommandWroteIsUndone(): void
    {
        $tenantId = Ids::next();

        try {
            $this->commandBus->dispatch(new RaiseEvents($tenantId, publish: false));
        } catch (\LogicException) {
        }

        self::assertSame(0, $this->tenantsWithId($tenantId), 'The guard runs inside the transaction, so the write is rolled back.');
    }

    public function testAPolicyThatLeavesEventsBehindFailsToo(): void
    {
        $tenantId = Ids::next();

        try {
            self::getContainer()->get(EventBus::class)->publish(new ForgetfulTrigger($tenantId));
            self::fail('A policy that forgets its events should fail.');
        } catch (\LogicException $error) {
            self::assertStringContainsString(ForgetfulTrigger::class, $error->getMessage());
            self::assertStringContainsString('TenantRegistered', $error->getMessage());
        }

        self::assertSame(0, $this->tenantsWithId($tenantId), 'The policy runs in a transaction of its own, which is rolled back.');
    }

    private function tenantsWithId(string $tenantId): int
    {
        $count = $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM tenants WHERE id = ?', [$tenantId]);
        \assert(is_numeric($count));

        return (int) $count;
    }
}
