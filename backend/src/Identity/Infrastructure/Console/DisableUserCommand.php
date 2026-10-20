<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Console;

use App\Identity\Application\DisableUser\DisableUser;
use App\Identity\Application\DisableUser\DisableUserHandler;
use App\Shared\Domain\DomainError;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:user:disable', description: 'Stops a user from signing in, and stops the tokens it already holds')]
final class DisableUserCommand extends Command
{
    public function __construct(
        private readonly DisableUserHandler $handler,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'The email address of the user');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = $input->getArgument('email');
        \assert(\is_string($email));

        try {
            ($this->handler)(new DisableUser($email));
            $this->entityManager->flush();
        } catch (DomainError $error) {
            $io->error($error->getMessage());

            return Command::FAILURE;
        }

        $io->success(\sprintf('User %s is disabled.', $email));

        return Command::SUCCESS;
    }
}
