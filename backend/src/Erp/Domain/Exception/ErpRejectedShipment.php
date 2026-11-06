<?php

declare(strict_types=1);

namespace App\Erp\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * The ERP understood the confirmation of a shipment and refused it. Sending it again will not change
 * that: someone has to look at the order.
 */
final class ErpRejectedShipment extends DomainRuleViolation
{
    public static function withCode(string $reference, int $code, string $message): self
    {
        return new self(
            'erp.shipment_rejected',
            ['reference' => $reference, 'code' => $code, 'detail' => $message],
            \sprintf('The ERP refused the shipment of order %s with code %d: %s', $reference, $code, $message),
        );
    }
}
