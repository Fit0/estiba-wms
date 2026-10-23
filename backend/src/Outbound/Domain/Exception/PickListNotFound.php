<?php

declare(strict_types=1);

namespace App\Outbound\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * No pick list with this identifier exists for the tenant.
 */
final class PickListNotFound extends DomainRuleViolation
{
    public static function forId(string $id): self
    {
        return new self('outbound.pick_list_not_found', ['id' => $id], \sprintf('No pick list with id %s.', $id));
    }
}
