<?php

declare(strict_types=1);

namespace App\Outbound\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * A part of the shipping address is empty, too long, has control characters, or is not a valid country.
 */
final class InvalidShippingAddress extends DomainRuleViolation
{
    public static function forField(string $field, int $maxLength): self
    {
        return new self(
            'outbound.shipping_address_invalid',
            ['field' => $field, 'maxLength' => $maxLength],
            \sprintf('The %s of a shipping address is not valid. It needs %d characters at most, none of them control characters.', $field, $maxLength),
        );
    }
}
