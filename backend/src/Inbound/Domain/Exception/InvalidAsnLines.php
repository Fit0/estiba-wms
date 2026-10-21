<?php

declare(strict_types=1);

namespace App\Inbound\Domain\Exception;

use App\Inbound\Domain\ProductId;
use App\Shared\Domain\DomainRuleViolation;

/**
 * The lines of an ASN do not say clearly what is coming: there are none, or a product appears on
 * two of them.
 *
 * Protects INB-15.
 */
final class InvalidAsnLines extends DomainRuleViolation
{
    public static function none(): self
    {
        return new self(
            'inbound.asn_lines_required',
            [],
            'An ASN needs at least one line.',
        );
    }

    public static function repeatedProduct(ProductId $productId): self
    {
        return new self(
            'inbound.asn_product_repeated',
            ['productId' => $productId->value],
            \sprintf('Product %s appears on more than one line of the ASN.', $productId->value),
        );
    }
}
