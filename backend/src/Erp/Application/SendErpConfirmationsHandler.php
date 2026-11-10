<?php

declare(strict_types=1);

namespace App\Erp\Application;

use App\Shared\Application\Bus\JobHandler;
use App\Shared\Application\Clock;
use Psr\Log\LoggerInterface;

final readonly class SendErpConfirmationsHandler implements JobHandler
{
    public function __construct(
        private ErpOutboxSender $sender,
        private Clock $clock,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(SendErpConfirmations $message): SendReport
    {
        $report = $this->sender->send($this->clock->now());

        if ($report->sent > 0 || $report->toRetry > 0 || $report->failed > 0) {
            $this->logger->info('ERP confirmations sent.', ['sent' => $report->sent, 'toRetry' => $report->toRetry, 'failed' => $report->failed]);
        }

        return $report;
    }
}
