<?php

declare(strict_types=1);

namespace App\Outbound\Domain;

use App\Outbound\Domain\Exception\InvalidSscc;
use App\Outbound\Domain\Exception\SsccSerialsExhausted;
use App\Shared\Domain\Gs1CheckDigit;

/**
 * The Serial Shipping Container Code that identifies one parcel in the whole supply chain: 18
 * digits, the last one a GS1 check digit (OUT-19).
 *
 * It is the extension digit, the GS1 company prefix of whoever ships, a serial that company never
 * repeats, and the check digit.
 */
final readonly class Sscc implements \Stringable
{
    public const int LENGTH = 18;

    private const int MIN_PREFIX = 6;

    private const int MAX_PREFIX = 10;

    private function __construct(public string $value)
    {
    }

    /**
     * @throws InvalidSscc OUT-19
     */
    public static function fromString(string $value): self
    {
        $digits = trim($value);

        if (1 !== preg_match('/^\d{18}$/', $digits)
            || Gs1CheckDigit::for(substr($digits, 0, -1)) !== (int) substr($digits, -1)
        ) {
            throw InvalidSscc::forValue($value);
        }

        return new self($digits);
    }

    /**
     * Builds the SSCC of the next serial of a company: the serial is left-padded with zeros to fill
     * the 17 digits that come before the check digit.
     *
     * @throws InvalidSscc          when the extension digit or the prefix are not digits of the right size
     * @throws SsccSerialsExhausted when the serial needs more digits than the prefix leaves
     */
    public static function compose(int $extensionDigit, string $companyPrefix, int $serial): self
    {
        $prefixLength = \strlen($companyPrefix);
        if ($extensionDigit < 0 || $extensionDigit > 9
            || 1 !== preg_match('/^\d+$/', $companyPrefix)
            || $prefixLength < self::MIN_PREFIX || $prefixLength > self::MAX_PREFIX
            || $serial < 0
        ) {
            throw InvalidSscc::forValue(\sprintf('%d%s/%d', $extensionDigit, $companyPrefix, $serial));
        }

        $serialLength = self::LENGTH - 2 - $prefixLength;
        if (\strlen((string) $serial) > $serialLength) {
            throw SsccSerialsExhausted::forPrefix($companyPrefix);
        }

        $body = $extensionDigit.$companyPrefix.str_pad((string) $serial, $serialLength, '0', \STR_PAD_LEFT);

        return new self($body.Gs1CheckDigit::for($body));
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
