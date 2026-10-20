<?php

declare(strict_types=1);

namespace App\Tests\Functional\Shared\Messaging;

use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\QueryBus;
use App\Shared\Domain\DomainError;
use App\Tests\Functional\Shared\Messaging\Fixtures\IsInATransaction;
use App\Tests\Functional\Shared\Messaging\Fixtures\SaveTenant;
use App\Tests\Functional\Warehouse\Persistence\PersistenceTestCase;
use App\Tests\Unit\Support\Ids;
use Symfony\Component\Messenger\Exception\NoHandlerForMessageException;

/**
 * What the two buses promise (ADR 0009): a command runs in one transaction that commits or rolls
 * back as a whole, a query runs in none, and a domain error reaches the caller unwrapped.
 */
final class BusesTest extends PersistenceTestCase
{
    private CommandBus $commandBus;

    private QueryBus $queryBus;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->commandBus = self::getContainer()->get(CommandBus::class);
        $this->queryBus = self::getContainer()->get(QueryBus::class);
    }

    public function testACommandHandlerRunsInsideATransaction(): void
    {
        self::assertTrue($this->commandBus->dispatch(new SaveTenant(Ids::next())));
    }

    public function testACommandIsFlushedAndCommittedWhenItsHandlerReturns(): void
    {
        $tenantId = Ids::next();

        $this->commandBus->dispatch(new SaveTenant($tenantId));

        self::assertSame(1, $this->tenantsWithId($tenantId), 'The handler never flushed: the bus did.');
        self::assertFalse($this->entityManager->getConnection()->isTransactionActive(), 'The transaction was closed.');
    }

    public function testEverythingAHandlerWroteIsUndoneWhenItFails(): void
    {
        $tenantId = Ids::next();

        try {
            $this->commandBus->dispatch(new SaveTenant($tenantId, failAfterFlushing: true));
            self::fail('The failure should reach the caller.');
        } catch (\RuntimeException $error) {
            self::assertSame('Something broke after the write.', $error->getMessage());
        }

        self::assertSame(0, $this->tenantsWithId($tenantId), 'The write was flushed inside the transaction, and the transaction was rolled back.');
        self::assertFalse($this->entityManager->getConnection()->isTransactionActive());
    }

    public function testADomainErrorReachesTheCallerAsItWasThrown(): void
    {
        try {
            $this->commandBus->dispatch(new SaveTenant(Ids::next(), failWithADomainError: true));
            self::fail('The domain error should reach the caller.');
        } catch (DomainError $error) {
            self::assertSame('shared.test_rule_broken', $error->errorCode());
            self::assertSame(['rule' => 'bus-test'], $error->errorParameters());
        }
    }

    public function testAQueryRunsWithoutATransaction(): void
    {
        self::assertFalse($this->queryBus->ask(new IsInATransaction()));
    }

    public function testEachBusOnlyKnowsItsOwnHandlers(): void
    {
        $queryBus = self::getContainer()->get('query.bus');
        $commandBus = self::getContainer()->get('command.bus');

        try {
            $queryBus->dispatch(new SaveTenant(Ids::next()));
            self::fail('A command must not be handled by the query bus.');
        } catch (NoHandlerForMessageException) {
            // Expected: a query bus has no command handler, so nothing can change state through it.
        }

        $this->expectException(NoHandlerForMessageException::class);
        $commandBus->dispatch(new IsInATransaction());
    }

    private function tenantsWithId(string $tenantId): int
    {
        $count = $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM tenants WHERE id = ?', [$tenantId]);
        \assert(is_numeric($count));

        return (int) $count;
    }
}
