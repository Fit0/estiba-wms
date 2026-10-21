<?php

declare(strict_types=1);

namespace App\Inbound\Application\Putaway;

use App\Inbound\Domain\Asn;
use App\Inbound\Domain\AsnRepository;
use App\Inbound\Domain\Event\ReceiptClosed;
use App\Inbound\Domain\PutawayTaskRepository;
use App\Inbound\Domain\Receipt;
use App\Inbound\Domain\ReceiptRepository;
use App\Shared\Application\Bus\EventHandler;

/**
 * Whenever a receipt closes, its goods get putaway tasks, one for each kind of goods, with a
 * suggested location (INB-14).
 */
final readonly class PlanPutaway implements EventHandler
{
    public function __construct(
        private ReceiptRepository $receipts,
        private AsnRepository $asns,
        private PutawayTaskRepository $tasks,
        private PutawayPlanner $planner,
    ) {
    }

    public function __invoke(ReceiptClosed $event): void
    {
        $receipt = $this->receipts->find($event->receiptId);
        $asn = $this->asns->find($event->asnId);
        if (!$receipt instanceof Receipt || !$asn instanceof Asn) {
            throw new \LogicException('A receipt that just closed and its ASN must exist.');
        }

        foreach ($this->planner->plan($receipt, $asn->reference(), $event->occurredAt()) as $task) {
            $this->tasks->save($task);
        }
    }
}
