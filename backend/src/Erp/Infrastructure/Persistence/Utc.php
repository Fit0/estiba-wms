<?php

declare(strict_types=1);

namespace App\Erp\Infrastructure\Persistence;

/**
 * The database keeps the clock of an instant in UTC and no zone, so every instant goes in and out as that.
 */
final class Utc
{
    private const string FORMAT = 'Y-m-d H:i:s';

    private function __construct()
    {
    }

    public static function text(\DateTimeImmutable $at): string
    {
        return $at->setTimezone(new \DateTimeZone('UTC'))->format(self::FORMAT);
    }

    /**
     * @throws \UnexpectedValueException when the database gave something that is not a date with time
     */
    public static function parse(mixed $text): \DateTimeImmutable
    {
        if (!\is_string($text)) {
            throw new \UnexpectedValueException('The database gave a date that is not text.');
        }

        return new \DateTimeImmutable($text, new \DateTimeZone('UTC'));
    }
}
