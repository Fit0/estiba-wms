<?php

declare(strict_types=1);

namespace App\Tests\Functional\Shared\Persistence;

use App\Identity\Domain\Role;
use App\Identity\Domain\Tenant;
use App\Identity\Domain\User;
use App\Shared\Domain\TenantId;
use App\Shared\Infrastructure\Persistence\Doctrine\TenantFilter;
use App\Tests\Functional\Warehouse\Persistence\PersistenceTestCase;
use App\Tests\Unit\Identity\Domain\IdentityMother;
use App\Tests\Unit\Warehouse\Domain\WarehouseMother;
use App\Warehouse\Domain\Warehouse;
use App\Warehouse\Infrastructure\Persistence\Doctrine\DoctrineWarehouseRepository;
use Doctrine\ORM\Mapping\ClassMetadata;

/**
 * The second wall behind the use cases (ADR 0008): while the filter is on, a query cannot read the
 * rows of another tenant, even one that forgets to ask for the tenant.
 */
final class TenantFilterTest extends PersistenceTestCase
{
    private TenantId $acme;

    private TenantId $rival;

    private DoctrineWarehouseRepository $warehouses;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->acme = WarehouseMother::tenantId();
        $this->rival = WarehouseMother::tenantId();
        $this->warehouses = new DoctrineWarehouseRepository($this->entityManager);

        $this->warehouses->save(WarehouseMother::warehouse($this->acme, 'ACME'));
        $this->warehouses->save(WarehouseMother::warehouse($this->rival, 'RIVAL'));
        $this->entityManager->persist(IdentityMother::user('ana@acme.test', [Role::Admin], $this->acme));
        $this->entityManager->persist(IdentityMother::user('rita@rival.test', [Role::Admin], $this->rival));
        $this->flushAndForget();
    }

    public function testSeesEveryTenantWhileItIsOff(): void
    {
        self::assertSame(['ACME', 'RIVAL'], $this->warehouseCodes());
    }

    public function testShowsOnlyTheRowsOfTheTenantWhileItIsOn(): void
    {
        $this->enableFor($this->acme);

        self::assertSame(['ACME'], $this->warehouseCodes(), 'A plain query that forgets the tenant must still be filtered.');
        self::assertSame(['ana@acme.test'], $this->userEmails());
    }

    public function testFindByIdDoesNotReturnTheRowOfAnotherTenant(): void
    {
        $rivalId = $this->warehouses->findAllOf($this->rival)[0]->id();
        $this->entityManager->clear();
        $this->enableFor($this->acme);

        self::assertNull($this->warehouses->find($rivalId), 'find() by identifier must not cross the tenant boundary.');
    }

    public function testAQueryThatAsksForAnotherTenantFindsNothing(): void
    {
        $this->enableFor($this->acme);

        self::assertSame([], $this->warehouses->findAllOf($this->rival));
    }

    public function testFollowsTheTenantItWasLastGiven(): void
    {
        $this->enableFor($this->acme);
        self::assertSame(['ACME'], $this->warehouseCodes());

        $this->entityManager->clear();
        $this->enableFor($this->rival);
        self::assertSame(['RIVAL'], $this->warehouseCodes());
    }

    // A missing tenant is an error, never "all tenants".
    public function testFailsClosedWhenTheTenantWasNeverSet(): void
    {
        $this->entityManager->getFilters()->enable(TenantFilter::NAME);

        $this->expectException(\InvalidArgumentException::class);
        $this->warehouseCodes();
    }

    public function testStopsFilteringWhenSwitchedOff(): void
    {
        $this->enableFor($this->acme);
        $this->entityManager->getFilters()->disable(TenantFilter::NAME);

        self::assertSame(['ACME', 'RIVAL'], $this->warehouseCodes());
    }

    // Any entity that stores a tenant is filtered: a new one cannot be forgotten, nor name its column differently.
    public function testEveryEntityThatStoresATenantIsFiltered(): void
    {
        $filter = $this->enableFor($this->acme);
        $filtered = [];
        $unfiltered = [];

        foreach ($this->entityManager->getMetadataFactory()->getAllMetadata() as $metadata) {
            $storesATenant = null !== $this->columnNamed($metadata, 'tenant_id');

            if ($storesATenant) {
                self::assertTrue($metadata->hasField('tenantId'), \sprintf('%s stores tenant_id under a field that is not called tenantId, so the filter would skip it.', $metadata->getName()));
                self::assertStringContainsString('.tenant_id = ', $filter->addFilterConstraint($metadata, 't0'), $metadata->getName());
                $filtered[] = $metadata->getName();
            } else {
                self::assertSame('', $filter->addFilterConstraint($metadata, 't0'), $metadata->getName());
                $unfiltered[] = $metadata->getName();
            }
        }

        self::assertContains(Warehouse::class, $filtered);
        self::assertContains(User::class, $filtered);
        self::assertContains(Tenant::class, $unfiltered, 'A tenant is not owned by a tenant.');
    }

    private function enableFor(TenantId $tenantId): TenantFilter
    {
        $filter = $this->entityManager->getFilters()->enable(TenantFilter::NAME);
        \assert($filter instanceof TenantFilter);
        $filter->setParameter(TenantFilter::PARAMETER, $tenantId, 'tenant_id');

        return $filter;
    }

    /**
     * @return list<string>
     */
    private function warehouseCodes(): array
    {
        /** @var list<Warehouse> $warehouses */
        $warehouses = $this->entityManager->createQuery('SELECT w FROM '.Warehouse::class.' w ORDER BY w.code')->getResult();

        return array_map(static fn (Warehouse $warehouse): string => $warehouse->code()->value, $warehouses);
    }

    /**
     * @return list<string>
     */
    private function userEmails(): array
    {
        /** @var list<User> $users */
        $users = $this->entityManager->createQuery('SELECT u FROM '.User::class.' u ORDER BY u.email')->getResult();

        return array_map(static fn (User $user): string => $user->email()->value, $users);
    }

    /**
     * @param ClassMetadata<object> $metadata
     */
    private function columnNamed(ClassMetadata $metadata, string $column): ?string
    {
        foreach ($metadata->getFieldNames() as $field) {
            if ($metadata->getColumnName($field) === $column) {
                return $field;
            }
        }

        return null;
    }
}
