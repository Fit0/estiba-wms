<?php

declare(strict_types=1);

namespace App\Tests\Functional\Erp;

use App\Erp\Application\ErpOutboxSender;
use App\Erp\Application\OutboxItem;
use App\Erp\Application\RetryPolicy;
use App\Erp\Application\ShipmentConfirmation;
use App\Erp\Application\ShippedLine;
use App\Erp\Domain\Exception\ErpRejectedShipment;
use App\Erp\Domain\Exception\ErpUnavailable;
use App\Erp\Infrastructure\Persistence\DbalImportedOrders;
use App\Erp\Infrastructure\Persistence\DbalShipmentOutbox;
use App\Shared\Domain\TenantId;
use App\Tests\Functional\Outbound\Api\ShipmentApiTestCase;
use App\Tests\Support\FakeErpGateway;
use App\Tests\Unit\Support\Ids;
use Doctrine\DBAL\Connection;
use Psr\Log\NullLogger;

/**
 * A dispatch of an order that came from the ERP leaves a confirmation in the outbox, which is sent to the ERP, and
 * kept and retried if the ERP does not answer (ADR 0049). Everything is real except the ERP.
 */
final class ShipmentOutboxTest extends ShipmentApiTestCase
{
    private Connection $connection;

    private FakeErpGateway $erp;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = $this->entityManager()->getConnection();
        $this->erp = new FakeErpGateway();
    }

    public function testDispatchingAnOrderOfTheErpQueuesItsConfirmationAndSendingItTellsTheErp(): void
    {
        $this->imported('PED-1');
        $shipment = $this->packedShipment();

        $this->dispatch($shipment, 'SEUR', 'SE0042261187');
        $this->assertStatus(200);

        $rows = $this->connection->fetchAllAssociative('SELECT shipment_id, status, attempts, order_reference FROM erp_shipment_outbox');
        self::assertCount(1, $rows);
        self::assertSame($this->id($shipment), $rows[0]['shipment_id']);
        self::assertSame('pending', $rows[0]['status']);
        self::assertSame('PED-1', $rows[0]['order_reference']);

        $report = $this->sender()->send(new \DateTimeImmutable());

        self::assertSame(1, $report->sent);
        self::assertCount(1, $this->erp->received);
        $confirmation = $this->erp->received[0];
        self::assertSame('PED-1', $confirmation->orderReference);
        self::assertSame('SEUR', $confirmation->carrier);
        self::assertSame('SE0042261187', $confirmation->trackingNumber);
        self::assertSame(1, $confirmation->parcels);
        self::assertEquals([new ShippedLine('CAM-001', 10)], $confirmation->lines);
        self::assertSame('sent', $this->connection->fetchOne('SELECT status FROM erp_shipment_outbox'));
        self::assertNotNull($this->connection->fetchOne('SELECT sent_at FROM erp_shipment_outbox'));
    }

    public function testAnOrderThatDidNotComeFromTheErpLeavesNothingInTheOutbox(): void
    {
        $this->dispatch($this->packedShipment(), 'SEUR');
        $this->assertStatus(200);

        self::assertEquals(0, $this->connection->fetchOne('SELECT COUNT(*) FROM erp_shipment_outbox'));
    }

    public function testADispatchDoesNotDependOnTheErp(): void
    {
        $this->imported('PED-1');
        $shipment = $this->packedShipment();

        $dispatched = $this->dispatch($shipment, 'SEUR');

        $this->assertStatus(200);
        self::assertSame('dispatched', $dispatched['status']);
        self::assertSame([], $this->erp->received, 'Nobody called the ERP while the shipment was dispatched.');
    }

    public function testQueueingAShipmentTwiceKeepsTheFirst(): void
    {
        $outbox = new DbalShipmentOutbox($this->connection);
        $tenant = TenantId::fromString($this->tenant);
        $shipmentId = Ids::next();
        $now = new \DateTimeImmutable('2026-11-09 10:00:00', new \DateTimeZone('UTC'));

        $outbox->enqueue($tenant, $shipmentId, $this->confirmation('P1'), $now);
        $outbox->markSent($shipmentId, $now);
        $outbox->enqueue($tenant, $shipmentId, $this->confirmation('P1'), $now);

        self::assertEquals(1, $this->connection->fetchOne('SELECT COUNT(*) FROM erp_shipment_outbox'));
        self::assertSame([], $outbox->due($now, 10), 'A confirmation that was sent does not come back.');
    }

    public function testOnlyWhatIsPendingAndDueComesOutOldestFirst(): void
    {
        $outbox = new DbalShipmentOutbox($this->connection);
        $tenant = TenantId::fromString($this->tenant);
        $now = new \DateTimeImmutable('2026-11-09 10:00:00', new \DateTimeZone('UTC'));
        [$first, $second, $later, $sent, $failed] = [Ids::next(), Ids::next(), Ids::next(), Ids::next(), Ids::next()];

        $outbox->enqueue($tenant, $second, $this->confirmation('P2'), $now->modify('-1 minute'));
        $outbox->enqueue($tenant, $first, $this->confirmation('P1'), $now->modify('-2 minutes'));
        $outbox->enqueue($tenant, $later, $this->confirmation('P3'), $now->modify('+1 minute'));
        $outbox->enqueue($tenant, $sent, $this->confirmation('P4'), $now->modify('-3 minutes'));
        $outbox->markSent($sent, $now);
        $outbox->enqueue($tenant, $failed, $this->confirmation('P5'), $now->modify('-3 minutes'));
        $outbox->markFailed($failed, 3, 'refused');

        $due = $outbox->due($now, 10);

        self::assertSame([$first, $second], array_map(static fn (OutboxItem $item): string => $item->shipmentId, $due));
        self::assertEquals($this->confirmation('P1'), $due[0]->confirmation);
        self::assertSame(0, $due[0]->attempts);
        self::assertTrue($due[0]->tenantId->equals($tenant));
        self::assertCount(1, $outbox->due($now, 1), 'The limit is respected.');
    }

    public function testARetryKeepsTheReasonAndTheCountAndWaits(): void
    {
        $outbox = new DbalShipmentOutbox($this->connection);
        $shipmentId = Ids::next();
        $now = new \DateTimeImmutable('2026-11-09 10:00:00', new \DateTimeZone('UTC'));
        $outbox->enqueue(TenantId::fromString($this->tenant), $shipmentId, $this->confirmation('P1'), $now);
        $this->erp->script = [ErpUnavailable::because('no answer')];

        $this->sender()->send($now);

        $row = $this->connection->fetchAssociative('SELECT status, attempts, last_error FROM erp_shipment_outbox');
        self::assertIsArray($row);
        self::assertSame('pending', $row['status']);
        self::assertSame(1, $row['attempts']);
        self::assertIsString($row['last_error']);
        self::assertStringContainsString('no answer', $row['last_error']);
        self::assertSame([], $outbox->due($now->modify('+30 seconds'), 10));
        self::assertCount(1, $outbox->due($now->modify('+61 seconds'), 10));
        self::assertSame(1, $outbox->due($now->modify('+61 seconds'), 10)[0]->attempts);
    }

    public function testARefusalLeavesItFailedWithTheReason(): void
    {
        $outbox = new DbalShipmentOutbox($this->connection);
        $now = new \DateTimeImmutable('2026-11-09 10:00:00', new \DateTimeZone('UTC'));
        $outbox->enqueue(TenantId::fromString($this->tenant), Ids::next(), $this->confirmation('P1'), $now);
        $this->erp->script = [ErpRejectedShipment::withCode('P1', 2, 'Pedido no encontrado')];

        $report = $this->sender()->send($now);

        self::assertSame(1, $report->failed);
        $row = $this->connection->fetchAssociative('SELECT status, attempts, last_error FROM erp_shipment_outbox');
        self::assertIsArray($row);
        self::assertSame('failed', $row['status']);
        self::assertIsString($row['last_error']);
        self::assertStringContainsString('Pedido no encontrado', $row['last_error']);
        self::assertSame([], $outbox->due($now->modify('+1 day'), 10));
    }

    private function imported(string $reference): void
    {
        new DbalImportedOrders($this->connection)->remember(TenantId::fromString($this->tenant), $reference, new \DateTimeImmutable());
    }

    private function sender(): ErpOutboxSender
    {
        return new ErpOutboxSender(new DbalShipmentOutbox($this->connection), $this->erp, new RetryPolicy(), new NullLogger());
    }

    private function confirmation(string $reference): ShipmentConfirmation
    {
        return new ShipmentConfirmation($reference, new \DateTimeImmutable('2026-11-09T10:00:00+00:00'), 'SEUR', null, 1, [new ShippedLine('CAM-001', 10)]);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function packedShipment(): array
    {
        $shipment = $this->startPacking();
        $this->closeParcel($shipment, 10);
        $packed = $this->request('POST', '/api/shipments/'.$this->id($shipment).'/complete');
        $this->assertStatus(200);

        return $packed;
    }

    /**
     * @param array<array-key, mixed> $shipment
     *
     * @return array<array-key, mixed>
     */
    private function dispatch(array $shipment, string $carrier, ?string $trackingNumber = null): array
    {
        return $this->request('POST', '/api/shipments/'.$this->id($shipment).'/dispatch', array_filter(['carrier' => $carrier, 'trackingNumber' => $trackingNumber], static fn (?string $value): bool => null !== $value));
    }
}
