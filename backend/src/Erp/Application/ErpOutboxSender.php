<?php

declare(strict_types=1);

namespace App\Erp\Application;

use App\Erp\Domain\Exception\ErpRejectedShipment;
use App\Erp\Domain\Exception\ErpUnavailable;
use Psr\Log\LoggerInterface;

/**
 * Tells the ERP about the shipments in the outbox that are due (ADR 0049). The ERP's answer decides what happens:
 * it took the confirmation, or already had it, and it is sent; it could not be reached, and it is tried again later,
 * with longer waits, until the policy gives up; it refused, and trying again would change nothing, so it fails at
 * once with the reason. One run does not go on after the ERP could not be reached, since the rest would only wait for
 * the same timeout; the next run takes them.
 */
final readonly class ErpOutboxSender
{
    private const int BATCH = 20;

    public function __construct(
        private ShipmentOutbox $outbox,
        private ErpGateway $erp,
        private RetryPolicy $policy,
        private LoggerInterface $logger,
    ) {
    }

    public function send(\DateTimeImmutable $now): SendReport
    {
        $sent = 0;
        $toRetry = 0;
        $failed = 0;

        foreach ($this->outbox->due($now, self::BATCH) as $item) {
            $attempts = $item->attempts + 1;

            try {
                $this->erp->confirmShipment($item->confirmation);
            } catch (ErpRejectedShipment $rejected) {
                $this->fail($item, $attempts, $rejected->getMessage());
                ++$failed;

                continue;
            } catch (ErpUnavailable $unavailable) {
                if ($this->policy->canTryAfter($attempts)) {
                    $this->outbox->scheduleRetry($item->shipmentId, $attempts, $this->policy->nextAttempt($attempts, $now), $unavailable->getMessage());
                    ++$toRetry;
                } else {
                    $this->fail($item, $attempts, $unavailable->getMessage());
                    ++$failed;
                }

                break;
            }

            $this->outbox->markSent($item->shipmentId, $now);
            ++$sent;
        }

        return new SendReport($sent, $toRetry, $failed);
    }

    private function fail(OutboxItem $item, int $attempts, string $reason): void
    {
        $this->outbox->markFailed($item->shipmentId, $attempts, $reason);
        $this->logger->warning('The ERP was not told that a shipment left.', ['shipment' => $item->shipmentId, 'order' => $item->confirmation->orderReference, 'attempts' => $attempts, 'reason' => $reason]);
    }
}
