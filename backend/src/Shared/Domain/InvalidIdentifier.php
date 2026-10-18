<?php

declare(strict_types=1);

namespace App\Shared\Domain;

final class InvalidIdentifier extends DomainRuleViolation
{
    /**
     * @param class-string<Identifier> $type
     */
    public static function forValue(string $type, string $value): self
    {
        $name = new \ReflectionClass($type)->getShortName();

        return new self(
            'shared.identifier_invalid',
            ['type' => $name, 'value' => $value],
            \sprintf('"%s" is not a valid %s. Expected a UUID.', $value, $name),
        );
    }
}
