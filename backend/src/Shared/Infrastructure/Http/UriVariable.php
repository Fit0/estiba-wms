<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

/**
 * Reads a variable of the request path, which API Platform hands over as `mixed`.
 */
final class UriVariable
{
    /**
     * @param array<string, mixed> $uriVariables
     */
    public static function string(array $uriVariables, string $name): string
    {
        $value = $uriVariables[$name] ?? null;

        if (!\is_string($value)) {
            throw new \LogicException(\sprintf('The route has no "%s" variable.', $name));
        }

        return $value;
    }
}
