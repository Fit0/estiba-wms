<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Console;

use App\Inventory\Application\RebuildStockOverview\RebuildStockOverview;
use App\Shared\Application\Bus\CommandBus;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:stock-overview:rebuild', description: 'Builds the stock read model again from the ledger, for every tenant')]
final class RebuildStockOverviewCommand extends Command
{
    public function __construct(
        private readonly CommandBus $commandBus,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $projected = $this->commandBus->dispatch(new RebuildStockOverview());

        new SymfonyStyle($input, $output)->success(\sprintf('The stock read model holds %d stock items.', $projected));

        return Command::SUCCESS;
    }
}
