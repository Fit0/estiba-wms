<?php

declare(strict_types=1);

namespace App\Erp\Infrastructure\Console;

use App\Erp\Application\ImportErpOrders;
use App\Erp\Application\ImportErpOrdersHandler;
use App\Erp\Application\ImportReport;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:erp:import-orders', description: 'Imports the order files that the ERP has left, once, now')]
final class ImportErpOrdersCommand extends Command
{
    public function __construct(private readonly ImportErpOrdersHandler $import)
    {
        parent::__construct();
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $report = ($this->import)(new ImportErpOrders());

        if (!$report instanceof ImportReport) {
            $io->warning('The import is not set up: give ERP_TENANT_ID and ERP_WAREHOUSE_CODE.');

            return Command::FAILURE;
        }

        $io->success(\sprintf('%d orders registered, %d already registered, %d files rejected.', $report->registered, $report->alreadyRegistered, $report->rejectedFiles));

        return Command::SUCCESS;
    }
}
