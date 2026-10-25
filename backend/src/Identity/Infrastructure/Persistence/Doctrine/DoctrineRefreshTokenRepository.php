<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence\Doctrine;

use App\Identity\Domain\RefreshToken;
use App\Identity\Domain\RefreshTokenRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Stages the token with the unit of work. It does not flush: the caller that owns the transaction does.
 */
final readonly class DoctrineRefreshTokenRepository implements RefreshTokenRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    #[\Override]
    public function findBySecretHash(string $secretHash): ?RefreshToken
    {
        return $this->entityManager->getRepository(RefreshToken::class)->findOneBy(['secretHash' => $secretHash]);
    }

    #[\Override]
    public function findLiveInFamily(string $familyId): array
    {
        /** @var list<RefreshToken> */
        return $this->entityManager->createQueryBuilder()
            ->select('t')
            ->from(RefreshToken::class, 't')
            ->where('t.familyId = :family')
            ->andWhere('t.revokedAt IS NULL')
            ->setParameter('family', $familyId)
            ->getQuery()
            ->getResult();
    }

    #[\Override]
    public function save(RefreshToken $token): void
    {
        $this->entityManager->persist($token);
    }
}
