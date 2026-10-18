<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * A business rule was broken.
 *
 * The code is the contract with the outside world: it never changes and it carries
 * no language. The API turns it into a translated message (ADR 0004).
 */
interface DomainError extends \Throwable
{
    /**
     * Lowercase and dotted, for example `inventory.stock_negative`.
     *
     * @return non-empty-string
     */
    public function errorCode(): string;

    /**
     * Values the translated message can mention.
     *
     * @return array<string, bool|float|int|string>
     */
    public function errorParameters(): array;
}
