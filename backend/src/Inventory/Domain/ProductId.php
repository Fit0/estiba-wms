<?php

declare(strict_types=1);

namespace App\Inventory\Domain;

use App\Shared\Domain\Identifier;

/**
 * A product, seen from Inventory. Only the identifier is held: the product lives in Catalog (ADR 0003).
 */
final readonly class ProductId extends Identifier
{
}
