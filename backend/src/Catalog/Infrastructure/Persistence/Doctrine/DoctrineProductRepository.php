<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence\Doctrine;

use App\Catalog\Domain\Gtin;
use App\Catalog\Domain\Product;
use App\Catalog\Domain\ProductId;
use App\Catalog\Domain\ProductRepository;
use App\Catalog\Domain\ProductStatus;
use App\Catalog\Domain\Sku;
use App\Catalog\Infrastructure\Persistence\GtinIndex\ProductGtinRecord;
use App\Shared\Domain\Page;
use App\Shared\Domain\PageRequest;
use App\Shared\Domain\TenantId;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

/**
 * Stages the product with the unit of work. It does not flush: the caller that owns the
 * transaction does. Saving also stages a row for each GTIN the product gained, which is
 * what the database uses to keep a GTIN unique per tenant (CAT-02).
 */
final readonly class DoctrineProductRepository implements ProductRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    #[\Override]
    public function find(ProductId $id): ?Product
    {
        return $this->entityManager->find(Product::class, $id);
    }

    #[\Override]
    public function findByGtin(TenantId $tenantId, Gtin $gtin): ?Product
    {
        $record = $this->entityManager->find(ProductGtinRecord::class, [
            'tenantId' => $tenantId->value,
            'gtin' => $gtin->normalized,
        ]);

        return $record instanceof ProductGtinRecord ? $this->find(ProductId::fromString($record->productId)) : null;
    }

    #[\Override]
    public function search(TenantId $tenantId, ?string $skuPrefix, ?ProductStatus $status, PageRequest $page): Page
    {
        $total = $this->filtered($tenantId, $skuPrefix, $status)
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();

        /** @var list<Product> $items */
        $items = $this->filtered($tenantId, $skuPrefix, $status)
            ->select('p')
            ->orderBy('p.sku')
            ->setFirstResult($page->offset())
            ->setMaxResults($page->itemsPerPage)
            ->getQuery()
            ->getResult();

        return new Page($items, (int) $total, $page);
    }

    #[\Override]
    public function save(Product $product): void
    {
        $this->entityManager->persist($product);

        foreach ($this->newGtins($product) as $gtin) {
            $this->entityManager->persist(new ProductGtinRecord($product->tenantId()->value, $gtin->normalized, $product->id()->value));
        }
    }

    #[\Override]
    public function isSkuTaken(TenantId $tenantId, Sku $sku): bool
    {
        $count = $this->entityManager->createQueryBuilder()
            ->select('COUNT(p.id)')
            ->from(Product::class, 'p')
            ->where('p.tenantId = :tenant')
            ->andWhere('p.sku = :sku')
            ->setParameter('tenant', $tenantId, 'tenant_id')
            ->setParameter('sku', $sku, 'sku')
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count > 0;
    }

    #[\Override]
    public function isGtinTaken(TenantId $tenantId, Gtin $gtin): bool
    {
        return null !== $this->entityManager->find(ProductGtinRecord::class, [
            'tenantId' => $tenantId->value,
            'gtin' => $gtin->normalized,
        ]);
    }

    /**
     * @return list<Gtin>
     */
    private function newGtins(Product $product): array
    {
        $known = $this->entityManager->createQueryBuilder()
            ->select('r.gtin')
            ->from(ProductGtinRecord::class, 'r')
            ->where('r.productId = :product')
            ->setParameter('product', $product->id()->value)
            ->getQuery()
            ->getSingleColumnResult();

        return array_values(array_filter(
            $product->gtins(),
            static fn (Gtin $gtin): bool => !\in_array($gtin->normalized, $known, true),
        ));
    }

    private function filtered(TenantId $tenantId, ?string $skuPrefix, ?ProductStatus $status): QueryBuilder
    {
        $query = $this->entityManager->createQueryBuilder()
            ->from(Product::class, 'p')
            ->where('p.tenantId = :tenant')
            ->setParameter('tenant', $tenantId, 'tenant_id');

        if (null !== $skuPrefix && '' !== trim($skuPrefix)) {
            $escaped = addcslashes(strtoupper(trim($skuPrefix)), '%_\\');
            $query->andWhere('p.sku LIKE :prefix')->setParameter('prefix', $escaped.'%');
        }

        if ($status instanceof ProductStatus) {
            $query->andWhere('p.status = :status')->setParameter('status', $status);
        }

        return $query;
    }
}
