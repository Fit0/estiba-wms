<?php

declare(strict_types=1);

namespace App\Outbound\Domain;

use App\Outbound\Domain\Exception\InvalidShippingAddress;

/**
 * Where the goods go. The country is the two letters of ISO 3166-1, in capitals. The rest is
 * typed by users, so it is stored as given.
 */
final readonly class ShippingAddress
{
    private const int MAX_STREET = 120;

    private const int MAX_POSTAL_CODE = 12;

    private const int MAX_CITY = 60;

    private function __construct(
        public string $street,
        public string $postalCode,
        public string $city,
        public string $countryCode,
    ) {
    }

    /**
     * @throws InvalidShippingAddress
     */
    public static function of(string $street, string $postalCode, string $city, string $countryCode): self
    {
        $country = strtoupper(trim($countryCode));
        if (1 !== preg_match('/^[A-Z]{2}$/', $country)) {
            throw InvalidShippingAddress::forField('countryCode', 2);
        }

        return new self(
            self::text('street', $street, self::MAX_STREET),
            self::text('postalCode', $postalCode, self::MAX_POSTAL_CODE),
            self::text('city', $city, self::MAX_CITY),
            $country,
        );
    }

    private static function text(string $field, string $value, int $maxLength): string
    {
        $trimmed = trim($value);
        $length = mb_strlen($trimmed);

        if ($length < 1 || $length > $maxLength || 1 === preg_match('/\p{Cc}/u', $trimmed)) {
            throw InvalidShippingAddress::forField($field, $maxLength);
        }

        return $trimmed;
    }
}
