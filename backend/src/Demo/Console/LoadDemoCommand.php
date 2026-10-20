<?php

declare(strict_types=1);

namespace App\Demo\Console;

use App\Demo\Fashion\DemoSummary;
use App\Demo\Fashion\FashionDemoLoader;
use App\Identity\Infrastructure\Console\PasswordPrompt;
use App\Shared\Domain\DomainError;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\When;

#[When(env: 'dev')]
#[When(env: 'test')]
#[AsCommand(name: 'app:demo:load', description: 'Loads a fashion company with its distribution centre and its collection')]
final class LoadDemoCommand extends Command
{
    public function __construct(
        private readonly FashionDemoLoader $loader,
        private readonly EntityManagerInterface $entityManager,
        private readonly PasswordPrompt $passwords,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this->setHelp(\sprintf(
            'Creates the tenant "%s" with an admin, a supervisor and an operator, a distribution centre in Zaragoza and a spring-summer collection. All three users share one password, asked for with the typing hidden or read from %s. Loading twice does nothing.',
            FashionDemoLoader::TENANT_NAME,
            PasswordPrompt::ENVIRONMENT_VARIABLE,
        ));
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $summary = $this->loader->load($this->passwords->ask($input, $io));
            $this->entityManager->flush();
        } catch (DomainError $error) {
            $io->error($error->getMessage());

            return Command::FAILURE;
        }

        if (!$summary instanceof DemoSummary) {
            $io->note(\sprintf('The demo is already loaded (%s exists). Nothing was changed.', FashionDemoLoader::ADMIN_EMAIL));

            return Command::SUCCESS;
        }

        $io->success(\sprintf('Loaded %s: %d zones, %d locations and %d products in warehouse %s.', $summary->tenantName, $summary->zones, $summary->locations, $summary->products, $summary->warehouseCode));
        $io->definitionList(['Tenant id' => $summary->tenantId], ...array_map(
            static fn (string $email, string $role): array => [$role => $email],
            array_keys($summary->users),
            $summary->users,
        ));

        return Command::SUCCESS;
    }
}
