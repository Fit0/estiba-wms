<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence\Doctrine;

use App\Identity\Domain\Email;
use App\Identity\Domain\Role;
use App\Identity\Domain\User;
use App\Identity\Domain\UserId;
use App\Identity\Domain\UserRepository;
use App\Identity\Domain\UserStatus;
use App\Shared\Domain\Page;
use App\Shared\Domain\PageRequest;
use App\Shared\Domain\TenantId;
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
    public function search(TenantId $tenantId, PageRequest $page): Page
    {
        $total = $this->entityManager->createQueryBuilder()
            ->select('COUNT(u.id)')
            ->from(User::class, 'u')
            ->where('u.tenantId = :tenant')
            ->setParameter('tenant', $tenantId, 'tenant_id')
            ->getQuery()
            ->getSingleScalarResult();

        /** @var list<User> $items */
        $items = $this->entityManager->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->where('u.tenantId = :tenant')
            ->setParameter('tenant', $tenantId, 'tenant_id')
            ->orderBy('u.email', 'ASC')
            ->setFirstResult($page->offset())
            ->setMaxResults($page->itemsPerPage)
            ->getQuery()
            ->getResult();

        return new Page($items, (int) $total, $page);
    }

    #[\Override]
    public function countIn(TenantId $tenantId): int
    {
        // A plain query on the column: the roles are a JSON list and the rule is only about the admin.
        $count = $this->entityManager->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM users WHERE tenant_id = ? AND status = ? AND roles::jsonb @> ?::jsonb',
            [$tenantId->value, UserStatus::Active->value, json_encode([Role::Admin->value], \JSON_THROW_ON_ERROR)],
        );

        return is_numeric($count) ? (int) $count : 0;
    }

    #[\Override]
    public function isTaken(Email $email): bool
    {
        // A plain query: the tenant filter must not hide the users of other tenants from a
        // rule that holds for the whole system.
        return false !== $this->entityManager->getConnection()->fetchOne('SELECT 1 FROM users WHERE email = ?', [$email->value]);
    }
}
