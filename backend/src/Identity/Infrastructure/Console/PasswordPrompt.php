<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Console;

use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Gets a password for a console command without ever taking it as an argument, which would leave it in
 * the shell history and in the process list.
 *
 * It is read from the `ESTIBA_PASSWORD` environment variable, for scripts, or asked for with the
 * typing hidden and confirmed.
 */
final readonly class PasswordPrompt
{
    public const string ENVIRONMENT_VARIABLE = 'ESTIBA_PASSWORD';

    public function ask(InputInterface $input, SymfonyStyle $io): string
    {
        $fromEnvironment = $_SERVER[self::ENVIRONMENT_VARIABLE] ?? $_ENV[self::ENVIRONMENT_VARIABLE] ?? null;
        if (\is_string($fromEnvironment) && '' !== $fromEnvironment) {
            return $fromEnvironment;
        }

        if (!$input->isInteractive()) {
            throw new RuntimeException(\sprintf('Set %s, or run the command in a terminal to be asked for the password.', self::ENVIRONMENT_VARIABLE));
        }

        $password = $io->askHidden('Password');
        if (!\is_string($password) || $password !== $io->askHidden('Repeat the password')) {
            throw new RuntimeException('The passwords do not match.');
        }

        return $password;
    }
}
