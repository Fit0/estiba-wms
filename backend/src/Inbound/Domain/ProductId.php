<?php

declare(strict_types=1);

namespace App\Inbound\Domain;

use App\Shared\Domain\Identifier;

/**
 * A product, seen from Inbound. Only the identifier is held: the product lives in Catalog (ADR 0003).
 */
final readonly class ProductId extends Identifier
{
}
