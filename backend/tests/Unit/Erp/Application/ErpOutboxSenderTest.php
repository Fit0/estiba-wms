<?php

declare(strict_types=1);

namespace App\Tests\Unit\Erp\Application;

use App\Erp\Application\ConfirmationOutcome;
use App\Erp\Application\ErpOutboxSender;
use App\Erp\Application\RetryPolicy;
use App\Erp\Application\ShipmentConfirmation;
use App\Erp\Application\ShippedLine;
use App\Erp\Domain\Exception\ErpRejectedShipment;
use App\Erp\Domain\Exception\ErpUnavailable;
use App\Shared\Domain\TenantId;
use App\Tests\Support\FakeErpGateway;
use App\Tests\Support\InMemoryShipmentOutbox;
use App\Tests\Support\OutboxRecord;
use App\Tests\Unit\Support\Ids;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class ErpOutboxSenderTest extends TestCase
{
    private InMemoryShipmentOutbox $outbox;

    private FakeErpGateway $erp;

    private \DateTimeImmutable $now;

    #[\Override]
    protected function setUp(): void
    {
        $this->outbox = new InMemoryShipmentOutbox();
        $this->erp = new FakeErpGateway();
        $this->now = new \DateTimeImmutable('2026-11-09 10:00:00', new \DateTimeZone('UTC'));
    }

    public function testSendsWhatIsDueAndMarksItSent(): void
    {
        $this->queue('s1', 'P1');
        $this->queue('s2', 'P2');

        $report = $this->sender()->send($this->now);

        self::assertSame(2, $report->sent);
        self::assertSame(['P1', 'P2'], array_map(static fn (ShipmentConfirmation $confirmation): string => $confirmation->orderReference, $this->erp->received));
        self::assertSame(['sent', 'sent'], array_map(static fn (OutboxRecord $row): string => $row->status, array_values($this->outbox->rows)));
    }

    public function testAnOrderTheErpAlreadyHadIsSentAsWell(): void
    {
        $this->queue('s1', 'P1');
        $this->erp->script = [ConfirmationOutcome::AlreadyConfirmed];

        $report = $this->sender()->send($this->now);

        self::assertSame(1, $report->sent);
        self::assertSame('sent', $this->outbox->rows['s1']->status);
    }

    public function testWhenTheErpIsDownItWaitsAndTheRestWaitToo(): void
    {
        $this->queue('s1', 'P1');
        $this->queue('s2', 'P2');
        $this->erp->script = [ErpUnavailable::because('no answer')];

        $report = $this->sender()->send($this->now);

        self::assertSame(1, $report->toRetry);
        self::assertSame(0, $report->sent);
        self::assertCount(1, $this->erp->received, 'After the ERP did not answer the run does not ask again.');
        self::assertSame('pending', $this->outbox->rows['s1']->status);
        self::assertSame(1, $this->outbox->rows['s1']->attempts);
        self::assertSame('2026-11-09 10:01:00', $this->outbox->rows['s1']->next->format('Y-m-d H:i:s'));
        self::assertSame(0, $this->outbox->rows['s2']->attempts);
    }

    public function testWhatWaitsIsNotSentBeforeItsTime(): void
    {
        $this->queue('s1', 'P1');
        $this->erp->script = [ErpUnavailable::because('no answer')];
        $this->sender()->send($this->now);

        $report = $this->sender()->send($this->now->modify('+30 seconds'));

        self::assertSame(0, $report->sent + $report->toRetry + $report->failed);
        self::assertCount(1, $this->erp->received);

        $later = $this->sender()->send($this->now->modify('+61 seconds'));

        self::assertSame(1, $later->sent);
        self::assertSame('sent', $this->outbox->rows['s1']->status);
    }

    public function testARefusalFailsAtOnceWithTheReasonAndTheRestGoOn(): void
    {
        $this->queue('s1', 'P1');
        $this->queue('s2', 'P2');
        $this->erp->script = [ErpRejectedShipment::withCode('P1', 2, 'Pedido no encontrado')];

        $report = $this->sender()->send($this->now);

        self::assertSame(1, $report->failed);
        self::assertSame(1, $report->sent);
        self::assertSame('failed', $this->outbox->rows['s1']->status);
        self::assertStringContainsString('Pedido no encontrado', (string) $this->outbox->rows['s1']->error);
        self::assertSame('sent', $this->outbox->rows['s2']->status);
    }

    public function testAfterTheLastAttemptItFails(): void
    {
        $this->queue('s1', 'P1');
        $now = $this->now;
        $failed = 0;

        for ($attempt = 1; $attempt <= RetryPolicy::MAX_ATTEMPTS; ++$attempt) {
            $this->erp->script = [ErpUnavailable::because('no answer')];
            $failed += $this->sender()->send($now)->failed;
            $now = $now->modify('+2 hours');
        }

        self::assertSame(1, $failed);
        self::assertSame('failed', $this->outbox->rows['s1']->status);
        self::assertSame(RetryPolicy::MAX_ATTEMPTS, $this->outbox->rows['s1']->attempts);
        self::assertCount(RetryPolicy::MAX_ATTEMPTS, $this->erp->received);
    }

    private function queue(string $shipmentId, string $reference): void
    {
        $this->outbox->enqueue(TenantId::fromString(Ids::next()), $shipmentId, new ShipmentConfirmation($reference, $this->now, 'SEUR', null, 1, [new ShippedLine('PV27-CAM-LIN-AZU-M', 2)]), $this->now);
    }

    private function sender(): ErpOutboxSender
    {
        return new ErpOutboxSender($this->outbox, $this->erp, new RetryPolicy(), new NullLogger());
    }
}
