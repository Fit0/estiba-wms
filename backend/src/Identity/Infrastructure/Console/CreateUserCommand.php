<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Console;

use App\Identity\Application\RegisterUser\RegisterUser;
use App\Identity\Application\RegisterUser\RegisterUserHandler;
use App\Shared\Domain\DomainError;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:user:create', description: 'Creates a user in an existing tenant')]
final class CreateUserCommand extends Command
{
    public function __construct(
        private readonly RegisterUserHandler $handler,
        private readonly EntityManagerInterface $entityManager,
        private readonly PasswordPrompt $passwords,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addArgument('tenant-id', InputArgument::REQUIRED, 'The identifier of the tenant the user belongs to')
            ->addArgument('email', InputArgument::REQUIRED, 'The email address the user signs in with')
            ->addOption('role', 'r', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'A role: admin, supervisor or operator. Repeat it for several.', ['operator'])
            ->setHelp('The password is asked for with the typing hidden, or read from the '.PasswordPrompt::ENVIRONMENT_VARIABLE.' environment variable.');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $tenantId = $input->getArgument('tenant-id');
        $email = $input->getArgument('email');
        $roles = array_values(array_filter((array) $input->getOption('role'), is_string(...)));
        \assert(\is_string($tenantId) && \is_string($email));

        try {
            $user = ($this->handler)(new RegisterUser($tenantId, $email, $this->passwords->ask($input, $io), $roles));
            $this->entityManager->flush();
        } catch (DomainError $error) {
            $io->error($error->getMessage());

            return Command::FAILURE;
        }

        $io->success(\sprintf('User %s created with the roles %s.', $user->email()->value, implode(', ', array_map(static fn ($role): string => $role->value, $user->roles()))));

        return Command::SUCCESS;
    }
}
