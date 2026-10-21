<?php

declare(strict_types=1);

namespace App\Tests\Functional\Shared\Messaging;

use App\Inbound\Domain\Event\PutawayConfirmed;
use App\Inbound\Domain\Event\ReceiptClosed;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Application\Bus\QueryBus;
use App\Shared\Domain\DomainError;
use App\Tests\Functional\Shared\Messaging\Fixtures\IsInATransaction;
use App\Tests\Functional\Shared\Messaging\Fixtures\NobodyListens;
use App\Tests\Functional\Shared\Messaging\Fixtures\PublishSomethingHappened;
use App\Tests\Functional\Shared\Messaging\Fixtures\SaveTenant;
use App\Tests\Functional\Warehouse\Persistence\PersistenceTestCase;
use App\Tests\Unit\Support\Ids;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\NoHandlerForMessageException;

/**
 * What the buses promise (ADR 0009, ADR 0012): a command runs in one transaction that commits or
 * rolls back as a whole, the events it publishes reach their policies only after it commits, each
 * policy runs in a transaction of its own, a query runs in none, and a domain error reaches the
 * caller unwrapped.
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

    public function testAPolicyRunsAfterTheCommandCommitsInATransactionOfItsOwn(): void
    {
        $commandTenant = Ids::next();
        $policyTenant = Ids::next();

        $this->commandBus->dispatch(new PublishSomethingHappened($commandTenant, $policyTenant));

        self::assertSame(1, $this->tenantsWithId($commandTenant));
        self::assertSame(1, $this->tenantsWithId($policyTenant), 'The event bus flushed what the policy wrote.');
    }

    // A policy that fails cannot undo a command that already committed: it is retried instead.
    public function testAFailingPolicyLeavesTheCommandCommittedAndReachesTheCallerAsItWasThrown(): void
    {
        $commandTenant = Ids::next();
        $policyTenant = Ids::next();

        try {
            $this->commandBus->dispatch(new PublishSomethingHappened($commandTenant, $policyTenant, policyFails: true));
            self::fail('The domain error of the policy should reach the caller.');
        } catch (DomainError $error) {
            self::assertSame('shared.test_rule_broken', $error->errorCode());
        }

        self::assertSame(1, $this->tenantsWithId($commandTenant), 'The command committed before the policy ran.');
        self::assertSame(0, $this->tenantsWithId($policyTenant), 'The policy rolled back what it wrote.');
        self::assertFalse($this->entityManager->getConnection()->isTransactionActive());
    }

    // A command that fails publishes nothing.
    public function testTheEventsOfACommandThatFailsAreDropped(): void
    {
        $commandTenant = Ids::next();
        $policyTenant = Ids::next();

        try {
            $this->commandBus->dispatch(new PublishSomethingHappened($commandTenant, $policyTenant, commandFailsAfterPublishing: true));
            self::fail('The domain error of the command should reach the caller.');
        } catch (DomainError $error) {
            self::assertSame('shared.test_rule_broken', $error->errorCode());
        }

        self::assertSame(0, $this->tenantsWithId($commandTenant));
        self::assertSame(0, $this->tenantsWithId($policyTenant), 'The policy never ran.');
    }

    /**
     * The policies that may run a moment later go to the async transport, which a worker consumes
     * with retries (ADR 0012). The tests swap that transport for a synchronous one.
     */
    public function testRoutesThePoliciesThatMayWaitToTheAsyncTransport(): void
    {
        $senders = self::getContainer()->get('messenger.senders_locator');

        foreach ([ReceiptClosed::class, PutawayConfirmed::class] as $event) {
            $envelope = new Envelope(new \ReflectionClass($event)->newInstanceWithoutConstructor());

            self::assertSame(['async'], array_keys(iterator_to_array($senders->getSenders($envelope))), $event);
        }

        self::assertSame([], iterator_to_array($senders->getSenders(new Envelope(new NobodyListens()))), 'Other events are handled at once.');
    }

    #[DoesNotPerformAssertions]
    public function testAnEventNobodyHandlesIsFine(): void
    {
        self::getContainer()->get(EventBus::class)->publish(new NobodyListens());
    }

    private function tenantsWithId(string $tenantId): int
    {
        $count = $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM tenants WHERE id = ?', [$tenantId]);
        \assert(is_numeric($count));

        return (int) $count;
    }
}
