<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Console;

use App\Identity\Application\RegisterTenant\RegisterTenant;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Domain\DomainError;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:tenant:create', description: 'Creates a tenant together with its first administrator')]
final class CreateTenantCommand extends Command
{
    public function __construct(
        private readonly CommandBus $commandBus,
        private readonly PasswordPrompt $passwords,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the company')
            ->addArgument('admin-email', InputArgument::REQUIRED, 'The email address the administrator signs in with')
            ->addOption('language', 'l', InputOption::VALUE_REQUIRED, 'The default language of the tenant: es or en', 'es')
            ->setHelp('The password is asked for with the typing hidden, or read from the '.PasswordPrompt::ENVIRONMENT_VARIABLE.' environment variable.');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = $input->getArgument('name');
        $email = $input->getArgument('admin-email');
        $language = $input->getOption('language');
        \assert(\is_string($name) && \is_string($email) && \is_string($language));

        try {
            $registered = $this->commandBus->dispatch(new RegisterTenant($name, $language, $email, $this->passwords->ask($input, $io)));
        } catch (DomainError $error) {
            $io->error($error->getMessage());

            return Command::FAILURE;
        }

        $io->success('Tenant created.');
        $io->definitionList(
            ['Tenant id' => $registered->tenant->id()->value],
            ['Name' => $registered->tenant->name()->value],
            ['Administrator' => $registered->admin->email()->value],
        );

        return Command::SUCCESS;
    }
}
