<?php

declare(strict_types=1);

namespace App\Inbound\Infrastructure\Persistence\Doctrine;

use App\Inbound\Domain\Receipt;
use App\Inbound\Domain\ReceiptId;
use App\Inbound\Domain\ReceiptRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Stages the receipt with the unit of work. It does not flush: the caller that owns the
 * transaction does.
 */
final readonly class DoctrineReceiptRepository implements ReceiptRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    #[\Override]
    public function find(ReceiptId $id): ?Receipt
    {
        return $this->entityManager->find(Receipt::class, $id);
    }

    #[\Override]
    public function save(Receipt $receipt): void
    {
        $this->entityManager->persist($receipt);
    }
}
