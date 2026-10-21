<?php

declare(strict_types=1);

namespace App\Inbound\Domain\Exception;

use App\Inbound\Domain\LocationId;
use App\Shared\Domain\DomainRuleViolation;

/**
 * Goods were put away where they cannot go: the location does not exist, is blocked, does not
 * store goods like these, is in a zone of another storage class, has no room for them, or is the
 * dock they come from.
 *
 * Protects INB-11.
 */
final class InvalidPutawayDestination extends DomainRuleViolation
{
    public static function unknown(string $locationId): self
    {
        return self::because($locationId, 'unknown');
    }

    public static function blocked(LocationId $locationId): self
    {
        return self::because($locationId->value, 'blocked');
    }

    public static function wrongType(LocationId $locationId): self
    {
        return self::because($locationId->value, 'wrong_type');
    }

    public static function storageClass(LocationId $locationId): self
    {
        return self::because($locationId->value, 'storage_class');
    }

    public static function noRoom(LocationId $locationId): self
    {
        return self::because($locationId->value, 'no_room');
    }

    public static function sameAsSource(LocationId $locationId): self
    {
        return self::because($locationId->value, 'same_as_source');
    }

    private static function because(string $locationId, string $reason): self
    {
        return new self(
            'inbound.putaway_destination_invalid',
            ['locationId' => $locationId, 'reason' => $reason],
            \sprintf('The goods cannot go to location %s (%s).', $locationId, $reason),
        );
    }
}
