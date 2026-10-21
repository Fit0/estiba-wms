<?php

declare(strict_types=1);

namespace App\Tests\Functional\Inbound\Persistence;

use App\Inbound\Domain\AsnReference;
use App\Inbound\Domain\AsnStatus;
use App\Inbound\Domain\Discrepancy;
use App\Inbound\Domain\DiscrepancyType;
use App\Inbound\Domain\ExpectedLine;
use App\Inbound\Domain\ItemCondition;
use App\Inbound\Domain\LotNumber;
use App\Inbound\Domain\Putaway\PutawayDestination;
use App\Inbound\Domain\PutawayTask;
use App\Inbound\Domain\PutawayTaskId;
use App\Inbound\Domain\PutawayTaskStatus;
use App\Inbound\Domain\ReceiptStatus;
use App\Inbound\Domain\ReceivedGoods;
use App\Inbound\Infrastructure\Persistence\Doctrine\DoctrineAsnRepository;
use App\Inbound\Infrastructure\Persistence\Doctrine\DoctrinePutawayTaskRepository;
use App\Inbound\Infrastructure\Persistence\Doctrine\DoctrineReceiptRepository;
use App\Shared\Domain\PageRequest;
use App\Tests\Functional\Warehouse\Persistence\PersistenceTestCase;
use App\Tests\Unit\Inbound\Domain\InboundMother;
use App\Tests\Unit\Support\Ids;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final class DoctrineInboundRepositoriesTest extends PersistenceTestCase
{
    private DoctrineAsnRepository $asns;

    private DoctrineReceiptRepository $receipts;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->asns = new DoctrineAsnRepository($this->entityManager);
        $this->receipts = new DoctrineReceiptRepository($this->entityManager);
    }

    public function testFindsASavedAsnWithItsLines(): void
    {
        $product = InboundMother::trackedProduct();
        $asn = InboundMother::asn([new ExpectedLine($product, 40, LotNumber::fromString('L-0425'), new \DateTimeImmutable('2027-03-31'))]);
        $this->asns->save($asn);
        $this->flushAndForget();

        $found = $this->asns->find($asn->id());

        self::assertNotNull($found);
        self::assertTrue($found->tenantId()->equals($asn->tenantId()));
        self::assertSame('ALB-2027-0153', $found->reference()->value);
        self::assertSame('Textiles del Sur', $found->supplier()->value);
        self::assertSame('2026-10-24', $found->expectedDate()->format('Y-m-d'));
        self::assertSame(AsnStatus::Expected, $found->status());
        self::assertNull($found->openReceiptId());
        self::assertEquals($asn->lines(), $found->lines());
    }

    // The ASN and its receipt change together, and the receipt keeps what it counted.
    public function testKeepsAReceiptAndWhatItsAsnLearnedFromIt(): void
    {
        $product = InboundMother::trackedProduct();
        $stranger = InboundMother::product();
        $asn = InboundMother::asn([new ExpectedLine($product, 40)]);
        $receipt = $asn->openReceipt(InboundMother::receiptId(), InboundMother::dock(), InboundMother::at());
        $receipt->receiveItem($product, 30, LotNumber::fromString('L-0425'), new \DateTimeImmutable('2027-03-31'), ItemCondition::Good, InboundMother::at());
        $receipt->receiveItem($product, 2, LotNumber::fromString('L-0425'), new \DateTimeImmutable('2027-03-31'), ItemCondition::Damaged, InboundMother::at());
        $receipt->receiveItem($stranger, 5, null, null, ItemCondition::Good, InboundMother::at());
        $this->asns->save($asn);
        $this->receipts->save($receipt);
        $this->flushAndForget();

        $found = $this->receipts->find($receipt->id());
        self::assertNotNull($found);
        self::assertSame(ReceiptStatus::Open, $found->status());
        self::assertTrue($found->asnId()->equals($asn->id()));
        self::assertTrue($found->dockLocationId()->equals($receipt->dockLocationId()));
        self::assertEquals($receipt->expected(), $found->expected());
        self::assertEquals($receipt->lines(), $found->lines());
        self::assertEquals($receipt->discrepancies(), $found->discrepancies());
        self::assertSame($receipt->openedAt()->format(\DATE_ATOM), $found->openedAt()->format(\DATE_ATOM));
        self::assertNull($found->closedAt());

        $reloadedAsn = $this->asns->find($asn->id());
        self::assertNotNull($reloadedAsn);
        self::assertNotNull($reloadedAsn->openReceiptId());
        self::assertTrue($receipt->id()->equals($reloadedAsn->openReceiptId()));

        // A change inside the lists is written too: the aggregate replaces them, it never edits them.
        $found->approveOverReceipt(InboundMother::at());
        $reloadedAsn->closeReceipt($found, InboundMother::operator(), InboundMother::at());
        $this->flushAndForget();

        $closed = $this->receipts->find($receipt->id());
        $asnAfter = $this->asns->find($asn->id());
        self::assertNotNull($closed);
        self::assertNotNull($asnAfter);
        self::assertSame(ReceiptStatus::Closed, $closed->status());
        self::assertTrue($closed->isOverReceiptApproved());
        self::assertNotNull($closed->closedAt());
        self::assertSame(AsnStatus::PartiallyReceived, $asnAfter->status());
        self::assertNull($asnAfter->openReceiptId());
        self::assertSame(32, $asnAfter->lines()[0]->receivedUnits);
        self::assertSame(
            [DiscrepancyType::Shortage, DiscrepancyType::UnexpectedProduct, DiscrepancyType::Damaged],
            array_map(static fn (Discrepancy $discrepancy): DiscrepancyType => $discrepancy->type, $closed->discrepancies()),
        );
    }

    // INB-03
    public function testKnowsWhichReferencesATenantHasUsed(): void
    {
        $asn = InboundMother::asn();
        $this->asns->save($asn);
        $this->flushAndForget();

        self::assertTrue($this->asns->isReferenceTaken($asn->tenantId(), AsnReference::fromString('ALB-2027-0153')));
        self::assertFalse($this->asns->isReferenceTaken($asn->tenantId(), AsnReference::fromString('ALB-2027-0154')));
        self::assertFalse($this->asns->isReferenceTaken(InboundMother::tenantId(), AsnReference::fromString('ALB-2027-0153')));
    }

    // INB-03: the database backs the registry check against two imports racing.
    public function testRejectsTheSameReferenceTwiceForATenant(): void
    {
        $first = InboundMother::asn();
        $this->asns->save($first);
        $this->flushAndForget();

        $this->asns->save(InboundMother::asn(null, $first->tenantId()));

        $this->expectException(UniqueConstraintViolationException::class);

        $this->entityManager->flush();
    }

    public function testListsTheAsnsOfATenantByStatusAndLatestFirst(): void
    {
        $tenantId = InboundMother::tenantId();
        $early = InboundMother::asn(null, $tenantId, 'ALB-1');
        $late = InboundMother::asn(null, $tenantId, 'ALB-2');
        $cancelled = InboundMother::asn(null, $tenantId, 'ALB-3');
        $cancelled->cancel(InboundMother::at());
        foreach ([$early, $late, $cancelled, InboundMother::asn(null, null, 'OTRO-1')] as $asn) {
            $this->asns->save($asn);
        }
        $this->flushAndForget();

        $all = $this->asns->search($tenantId, null, PageRequest::of());
        $expected = $this->asns->search($tenantId, AsnStatus::Expected, PageRequest::of(1, 1));

        self::assertSame(3, $all->total);
        self::assertSame(2, $expected->total);
        self::assertCount(1, $expected->items);
        self::assertSame('ALB-1', $expected->items[0]->reference()->value, 'Same expected date: ordered by reference.');
    }

    public function testKeepsAPutawayTaskAndWhereItsGoodsWent(): void
    {
        $tasks = new DoctrinePutawayTaskRepository($this->entityManager);
        $dock = InboundMother::dock();
        $shelf = InboundMother::dock();
        $task = PutawayTask::plan(
            PutawayTaskId::fromString(Ids::next()),
            InboundMother::tenantId(),
            InboundMother::receiptId(),
            AsnReference::fromString('ALB-2027-0153'),
            new ReceivedGoods(InboundMother::trackedProduct()->id, 12, LotNumber::fromString('L-0425'), new \DateTimeImmutable('2027-03-31'), true),
            $dock,
            null,
            InboundMother::at(),
        );
        $split = $task->split(PutawayTaskId::fromString(Ids::next()), 2, InboundMother::at());
        $tasks->save($task);
        $tasks->save($split);
        $this->flushAndForget();

        $found = $tasks->find($task->id());
        self::assertNotNull($found);
        self::assertEquals($task->goods(), $found->goods());
        self::assertNull($found->suggestedLocationId());
        self::assertSame(2, $tasks->search($task->tenantId(), $task->receiptId(), PutawayTaskStatus::Pending, PageRequest::of())->total);

        $found->confirm(new PutawayDestination($shelf, true, true, true, true), null, InboundMother::operator(), InboundMother::at());
        $this->flushAndForget();

        $confirmed = $tasks->find($task->id());
        self::assertNotNull($confirmed);
        self::assertSame(PutawayTaskStatus::Confirmed, $confirmed->status());
        self::assertTrue($shelf->equals($confirmed->confirmedLocationId() ?? $dock));
        self::assertSame(10, $confirmed->goods()->units);
        self::assertSame(1, $tasks->search($task->tenantId(), null, PutawayTaskStatus::Pending, PageRequest::of())->total);
    }
}
