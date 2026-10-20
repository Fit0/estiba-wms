<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Domain\PasswordHash;
use App\Identity\Domain\PasswordHasher;
use App\Identity\Domain\PlainPassword;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

/**
 * Hashes with the algorithm configured for {@see SecurityUser} in `security.yaml`, the same one
 * that later checks the password at login.
 */
final readonly class SymfonyPasswordHasher implements PasswordHasher
{
    public function __construct(private PasswordHasherFactoryInterface $hashers)
    {
    }

    #[\Override]
    public function hash(PlainPassword $password): PasswordHash
    {
        $hash = $this->hashers->getPasswordHasher(SecurityUser::class)->hash($password->reveal());

        if ('' === $hash) {
            throw new \LogicException('The password hasher returned an empty hash.');
        }

        return new PasswordHash($hash);
    }
}
