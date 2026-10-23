<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\Doctrine;

use App\Inventory\Domain\LocationId;
use App\Inventory\Domain\ProductId;
use App\Inventory\Domain\StockItem;
use App\Inventory\Domain\StockItemId;
use App\Inventory\Domain\StockItemRepository;
use App\Inventory\Domain\StockKey;
use App\Inventory\Domain\StockStatus;
use App\Shared\Domain\TenantId;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Stages the stock item and the movements it recorded with the unit of work, so both are written
 * in the same flush (STK-03). It does not flush: the caller that owns the transaction does.
 */
final readonly class DoctrineStockItemRepository implements StockItemRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    #[\Override]
    public function find(StockItemId $id): ?StockItem
    {
        return $this->entityManager->find(StockItem::class, $id);
    }

    /**
     * A stock item opened earlier in the same transaction is found too, though it is not in the
     * database yet, so two changes of the same key never open two stock items.
     */
    #[\Override]
    public function findByKey(TenantId $tenantId, StockKey $key): ?StockItem
    {
        foreach ($this->entityManager->getUnitOfWork()->getScheduledEntityInsertions() as $scheduled) {
            if ($scheduled instanceof StockItem && $scheduled->tenantId()->equals($tenantId) && $scheduled->key()->equals($key)) {
                return $scheduled;
            }
        }

        $found = $this->entityManager->createQueryBuilder()
            ->select('s')
            ->from(StockItem::class, 's')
            ->where('s.tenantId = :tenant')
            ->andWhere('s.key.productId = :product')
            ->andWhere('s.key.locationId = :location')
            ->andWhere('s.key.lot = :lot')
            ->andWhere('s.key.expiryDate = :expiryDate')
            ->andWhere('s.key.status = :status')
            ->setParameter('tenant', $tenantId, 'tenant_id')
            ->setParameter('product', $key->productId, 'stock_product_id')
            ->setParameter('location', $key->locationId, 'stock_location_id')
            ->setParameter('lot', $key->lot, 'stock_lot')
            ->setParameter('expiryDate', $key->expiryDate, 'stock_expiry_date')
            ->setParameter('status', $key->status)
            ->getQuery()
            ->getOneOrNullResult();

        return $found instanceof StockItem ? $found : null;
    }

    #[\Override]
    public function findAt(TenantId $tenantId, array $locationIds): array
    {
        if ([] === $locationIds) {
            return [];
        }

        /** @var list<StockItem> $items */
        $items = $this->entityManager->createQueryBuilder()
            ->select('s')
            ->from(StockItem::class, 's')
            ->where('s.tenantId = :tenant')
            ->andWhere('s.key.locationId IN (:locations)')
            ->andWhere('s.onHand > 0')
            ->setParameter('tenant', $tenantId, 'tenant_id')
            ->setParameter('locations', array_map(static fn (LocationId $id): string => $id->value, $locationIds))
            ->orderBy('s.key.locationId')
            ->getQuery()
            ->getResult();

        return $items;
    }

    #[\Override]
    public function findReservable(TenantId $tenantId, ProductId $productId): array
    {
        /** @var list<StockItem> $items */
        $items = $this->entityManager->createQueryBuilder()
            ->select('s')
            ->from(StockItem::class, 's')
            ->where('s.tenantId = :tenant')
            ->andWhere('s.key.productId = :product')
            ->andWhere('s.key.status = :status')
            ->andWhere('s.onHand > s.reserved')
            ->setParameter('tenant', $tenantId, 'tenant_id')
            ->setParameter('product', $productId, 'stock_product_id')
            ->setParameter('status', StockStatus::Available)
            ->orderBy('s.id')
            ->getQuery()
            ->getResult();

        return $items;
    }

    #[\Override]
    public function all(): iterable
    {
        /** @var iterable<StockItem> $items */
        $items = $this->entityManager->createQueryBuilder()
            ->select('s')
            ->from(StockItem::class, 's')
            ->orderBy('s.id')
            ->getQuery()
            ->toIterable();

        return $items;
    }

    #[\Override]
    public function save(StockItem $stockItem): void
    {
        $this->entityManager->persist($stockItem);

        foreach ($stockItem->pullMovements() as $movement) {
            $this->entityManager->persist($movement);
        }
    }
}
