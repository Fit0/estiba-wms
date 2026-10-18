<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * Base of every domain exception. A subclass names the rule it protects and builds
 * itself through a named constructor, so the code and its parameters live in one place.
 *
 * The message is for logs and developers, in English. Users never see it.
 */
abstract class DomainRuleViolation extends \DomainException implements DomainError
{
    /**
     * @param non-empty-string                     $errorCode
     * @param array<string, bool|float|int|string> $errorParameters
     */
    final protected function __construct(
        private readonly string $errorCode,
        private readonly array $errorParameters,
        string $message,
    ) {
        parent::__construct($message);
    }

    final public function errorCode(): string
    {
        return $this->errorCode;
    }

    final public function errorParameters(): array
    {
        return $this->errorParameters;
    }
}
