<?php

declare(strict_types=1);

namespace App\Inbound\Infrastructure\Http\Input;

use App\Inbound\Application\RegisterAsn\RegisterAsnLine;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Reads the lines of an ASN from the request body. A line of the wrong shape makes the request
 * unreadable (400), like any other field of the wrong type. Whether the values make sense is the
 * domain's call.
 */
final class RegisterAsnLines
{
    private const string DATE_FORMAT = 'Y-m-d';

    /**
     * @param array<array-key, mixed> $lines
     *
     * @return list<RegisterAsnLine>
     */
    public static function from(array $lines): array
    {
        $read = [];
        foreach (array_values($lines) as $position => $line) {
            $read[] = self::line($position + 1, $line);
        }

        return $read;
    }

    private static function line(int $number, mixed $line): RegisterAsnLine
    {
        if (!\is_array($line) || !\is_string($line['productId'] ?? null) || !\is_int($line['units'] ?? null)) {
            throw self::malformed($number, 'a text "productId" and a whole number "units"');
        }

        $lot = $line['lot'] ?? null;
        if (null !== $lot && !\is_string($lot)) {
            throw self::malformed($number, 'a text "lot"');
        }

        return new RegisterAsnLine($line['productId'], $line['units'], $lot, self::date($number, $line['expiryDate'] ?? null));
    }

    private static function date(int $number, mixed $value): ?\DateTimeImmutable
    {
        if (null === $value) {
            return null;
        }

        $date = \is_string($value) ? \DateTimeImmutable::createFromFormat('!'.self::DATE_FORMAT, $value) : false;
        if (false === $date || $date->format(self::DATE_FORMAT) !== $value) {
            throw self::malformed($number, 'an "expiryDate" written as YYYY-MM-DD');
        }

        return $date;
    }

    private static function malformed(int $number, string $what): BadRequestHttpException
    {
        return new BadRequestHttpException(\sprintf('Line %d of the ASN needs %s.', $number, $what));
    }
}
