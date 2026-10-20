<?php

declare(strict_types=1);

namespace App\Tests\Functional\Shared\Messaging\Fixtures;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class IsInATransactionHandler
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function __invoke(IsInATransaction $query): bool
    {
        return $this->entityManager->getConnection()->isTransactionActive();
    }
}
