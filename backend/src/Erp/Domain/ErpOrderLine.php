<?php

declare(strict_types=1);

namespace App\Erp\Domain;

/**
 * A line of an order as the ERP wrote it. The article is the ERP's code, which is the SKU of the
 * catalogue.
 */
final readonly class ErpOrderLine
{
    public function __construct(
        public int $number,
        public string $article,
        public int $units,
    ) {
    }
}
