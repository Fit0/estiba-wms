<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain;

use App\Catalog\Domain\Gtin;
use App\Catalog\Domain\GtinRegistry;
use App\Catalog\Domain\Sku;
use App\Catalog\Domain\SkuRegistry;
use App\Shared\Domain\TenantId;

/**
 * Stands in for the database in unit tests: it remembers which SKUs and GTINs are taken.
 */
final class InMemoryProductRegistry implements SkuRegistry, GtinRegistry
{
    /** @var array<string, true> */
    private array $skus = [];

    /** @var array<string, true> */
    private array $gtins = [];

    public function takeSku(TenantId $tenantId, Sku $sku): self
    {
        $this->skus[$tenantId->value.'|'.$sku->value] = true;

        return $this;
    }

    public function takeGtin(TenantId $tenantId, Gtin $gtin): self
    {
        $this->gtins[$tenantId->value.'|'.$gtin->normalized] = true;

        return $this;
    }

    public function isSkuTaken(TenantId $tenantId, Sku $sku): bool
    {
        return isset($this->skus[$tenantId->value.'|'.$sku->value]);
    }

    public function isGtinTaken(TenantId $tenantId, Gtin $gtin): bool
    {
        return isset($this->gtins[$tenantId->value.'|'.$gtin->normalized]);
    }
}
