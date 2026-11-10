<?php

declare(strict_types=1);

namespace App\Erp\Infrastructure\Console;

use App\Erp\Application\SendErpConfirmations;
use App\Erp\Application\SendErpConfirmationsHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:erp:send-confirmations', description: 'Tells the ERP about the shipments that left and are still waiting in the outbox, once, now')]
final class SendErpConfirmationsCommand extends Command
{
    public function __construct(private readonly SendErpConfirmationsHandler $send)
    {
        parent::__construct();
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $report = ($this->send)(new SendErpConfirmations());

        new SymfonyStyle($input, $output)->success(\sprintf('%d confirmations sent, %d to try again later, %d failed.', $report->sent, $report->toRetry, $report->failed));

        return Command::SUCCESS;
    }
}
