<?php

declare(strict_types=1);

namespace App\Demo\Fashion;

use App\Catalog\Domain\Dimensions;
use App\Catalog\Domain\Gtin;
use App\Catalog\Domain\PackagingLevel;
use App\Catalog\Domain\PackagingType;
use App\Catalog\Domain\Product;
use App\Catalog\Domain\ProductId;
use App\Catalog\Domain\ProductName;
use App\Catalog\Domain\ProductRepository;
use App\Catalog\Domain\Sku;
use App\Catalog\Domain\TrackingPolicy;
use App\Identity\Domain\Email;
use App\Identity\Domain\PasswordHasher;
use App\Identity\Domain\PlainPassword;
use App\Identity\Domain\Role;
use App\Identity\Domain\Tenant;
use App\Identity\Domain\TenantName;
use App\Identity\Domain\TenantRepository;
use App\Identity\Domain\User;
use App\Identity\Domain\UserId;
use App\Identity\Domain\UserRepository;
use App\Shared\Application\Clock;
use App\Shared\Application\IdGenerator;
use App\Shared\Domain\Language;
use App\Shared\Domain\StorageClass;
use App\Shared\Domain\TenantId;
use App\Warehouse\Domain\DisplayName;
use App\Warehouse\Domain\LocationCode;
use App\Warehouse\Domain\LocationId;
use App\Warehouse\Domain\LocationRepository;
use App\Warehouse\Domain\PickSequence;
use App\Warehouse\Domain\Warehouse;
use App\Warehouse\Domain\WarehouseCode;
use App\Warehouse\Domain\WarehouseId;
use App\Warehouse\Domain\WarehouseRepository;
use App\Warehouse\Domain\ZoneCode;
use App\Warehouse\Domain\ZoneId;
use Symfony\Component\DependencyInjection\Attribute\When;

/**
 * Loads a fashion company with its distribution centre and its collection, so the API, the
 * frontend and a demo have something real to show.
 *
 * It builds everything through the domain, with the same rules as any other data, so the demo can
 * never hold something the system would refuse. It exists only in `dev` and `test`: there is no
 * demo data in production.
 *
 * The caller owns the transaction and flushes.
 */
#[When(env: 'dev')]
#[When(env: 'test')]
final readonly class FashionDemoLoader
{
    public const string TENANT_NAME = 'Marea Moda';

    public const string ADMIN_EMAIL = 'admin@marea.test';

    public const string SUPERVISOR_EMAIL = 'supervisor@marea.test';

    public const string OPERATOR_EMAIL = 'operario@marea.test';

    public function __construct(
        private TenantRepository $tenants,
        private UserRepository $users,
        private WarehouseRepository $warehouses,
        private LocationRepository $locations,
        private ProductRepository $products,
        private PasswordHasher $hasher,
        private IdGenerator $ids,
        private Clock $clock,
        private FashionCatalog $catalog,
        private FashionLayout $layout,
    ) {
    }

    /**
     * @return DemoSummary|null null when the demo was already loaded: loading it twice does nothing
     */
    public function load(#[\SensitiveParameter] string $password): ?DemoSummary
    {
        if ($this->users->isTaken(Email::fromString(self::ADMIN_EMAIL))) {
            return null;
        }

        $now = $this->clock->now();
        $tenant = Tenant::register(TenantId::fromString($this->ids->next()), TenantName::fromString(self::TENANT_NAME), Language::Spanish, $now);
        $this->tenants->save($tenant);

        $users = $this->registerUsers($tenant, $password, $now);
        [$zones, $locations] = $this->buildWarehouse($tenant, $now);
        $products = $this->registerProducts($tenant, $now);

        return new DemoSummary($tenant->id()->value, self::TENANT_NAME, $users, FashionLayout::WAREHOUSE_CODE, $zones, $locations, $products);
    }

    /**
     * @return array<string, string>
     */
    private function registerUsers(Tenant $tenant, #[\SensitiveParameter] string $password, \DateTimeImmutable $now): array
    {
        $hash = $this->hasher->hash(PlainPassword::fromString($password));
        $created = [];

        foreach ([self::ADMIN_EMAIL => Role::Admin, self::SUPERVISOR_EMAIL => Role::Supervisor, self::OPERATOR_EMAIL => Role::Operator] as $email => $role) {
            $this->users->save(User::register(UserId::fromString($this->ids->next()), $tenant->id(), Email::fromString($email), $hash, [$role], $this->users, $now));
            $created[$email] = $role->value;
        }

        return $created;
    }

    /**
     * @return array{int, int} the number of zones and of locations
     */
    private function buildWarehouse(Tenant $tenant, \DateTimeImmutable $now): array
    {
        $warehouse = Warehouse::create(
            WarehouseId::fromString($this->ids->next()),
            $tenant->id(),
            WarehouseCode::fromString(FashionLayout::WAREHOUSE_CODE),
            DisplayName::fromString(FashionLayout::WAREHOUSE_NAME),
            $this->warehouses,
            $now,
        );

        /** @var array<string, ZoneId> $zoneIds */
        $zoneIds = [];
        foreach ($this->layout->zones() as $zone) {
            $zoneIds[$zone->code] = ZoneId::fromString($this->ids->next());
            $warehouse->addZone($zoneIds[$zone->code], ZoneCode::fromString($zone->code), DisplayName::fromString($zone->name), StorageClass::Ambient, PickSequence::of($zone->pickSequence), $now);
        }
        $this->warehouses->save($warehouse);

        $locations = $this->layout->locations();
        foreach ($locations as $location) {
            $this->locations->save($warehouse->defineLocation(
                LocationId::fromString($this->ids->next()),
                $zoneIds[$location->zone],
                LocationCode::fromString($location->code),
                $location->type,
                $location->capacity,
                PickSequence::of($location->pickSequence),
                $this->locations,
                $now,
            ));
        }

        return [\count($zoneIds), \count($locations)];
    }

    private function registerProducts(Tenant $tenant, \DateTimeImmutable $now): int
    {
        $count = 0;

        foreach ($this->catalog->products() as $demo) {
            $product = Product::register(
                ProductId::fromString($this->ids->next()),
                $tenant->id(),
                Sku::fromString($demo->sku),
                ProductName::fromString($demo->name),
                $demo->unit,
                Dimensions::of($demo->lengthMm, $demo->widthMm, $demo->heightMm, $demo->weightGrams),
                TrackingPolicy::of(false, false),
                StorageClass::Ambient,
                $this->products,
                $now,
            );
            $product->assignGtin(Gtin::fromString($demo->gtin), $this->products, $now);
            $product->definePackagingLevel(PackagingLevel::of(PackagingType::Case, $demo->unitsPerCase), $now);
            $product->definePackagingLevel(PackagingLevel::of(PackagingType::Pallet, $demo->unitsPerPallet), $now);

            $this->products->save($product);
            ++$count;
        }

        return $count;
    }
}
