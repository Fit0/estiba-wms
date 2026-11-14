<?php

declare(strict_types=1);

namespace App\Erp\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * There is no failed confirmation for this shipment: the shipment has none, it belongs to another tenant, or its
 * confirmation is not failed, so there is nothing to send again.
 */
final class FailedConfirmationNotFound extends DomainRuleViolation
{
    public static function forShipment(string $shipmentId): self
    {
        return new self(
            'erp.failed_confirmation_not_found',
            ['shipmentId' => $shipmentId],
            \sprintf('There is no failed confirmation for shipment %s to send again.', $shipmentId),
        );
    }
}
