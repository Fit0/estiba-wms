<?php

declare(strict_types=1);

namespace App\Identity\Domain;

use App\Identity\Domain\Exception\InvalidPassword;

/**
 * A password as someone typed it, for the short time between receiving it and hashing it.
 *
 * It is never stored, never logged and never printed: there is no way to turn it into text
 * except {@see reveal()}, which only the hasher calls. The length limit comes from bcrypt, which
 * ignores everything after 72 bytes (IDN-03).
 */
final readonly class PlainPassword
{
    public const int MIN_LENGTH = 12;
    public const int MAX_BYTES = 72;

    private function __construct(#[\SensitiveParameter] private string $value)
    {
    }

    /**
     * @throws InvalidPassword IDN-03
     */
    public static function fromString(#[\SensitiveParameter] string $value): self
    {
        if (mb_strlen($value) < self::MIN_LENGTH || \strlen($value) > self::MAX_BYTES) {
            throw InvalidPassword::forPolicy();
        }

        return new self($value);
    }

    public function reveal(): string
    {
        return $this->value;
    }

    public function __debugInfo(): array
    {
        return [];
    }
}
