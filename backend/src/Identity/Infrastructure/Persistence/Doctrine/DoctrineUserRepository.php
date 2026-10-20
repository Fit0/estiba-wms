<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence\Doctrine;

use App\Identity\Domain\Email;
use App\Identity\Domain\User;
use App\Identity\Domain\UserId;
use App\Identity\Domain\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Stages the user with the unit of work. It does not flush: the caller that owns the
 * transaction does.
 *
 * Signing in and the uniqueness of an email address look across every tenant, because they
 * happen before anyone is authenticated, or must hold for the whole system (IDN-01).
 */
final readonly class DoctrineUserRepository implements UserRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    #[\Override]
    public function find(UserId $id): ?User
    {
        return $this->entityManager->find(User::class, $id);
    }

    #[\Override]
    public function findByEmail(Email $email): ?User
    {
        /** @var User|null */
        return $this->entityManager->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->where('u.email = :email')
            ->setParameter('email', $email, 'email')
            ->getQuery()
            ->getOneOrNullResult();
    }

    #[\Override]
    public function save(User $user): void
    {
        $this->entityManager->persist($user);
    }

    #[\Override]
    public function isTaken(Email $email): bool
    {
        // A plain query: the tenant filter must not hide the users of other tenants from a
        // rule that holds for the whole system.
        return false !== $this->entityManager->getConnection()->fetchOne('SELECT 1 FROM users WHERE email = ?', [$email->value]);
    }
}
