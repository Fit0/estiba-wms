<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Realtime;

/**
 * A JSON Web Token signed with HMAC SHA-256, which is what a Mercure hub checks (RFC 7519). It only
 * does what the hub needs: sign a set of claims, and read them back if the signature is right and the
 * token has not expired.
 */
final readonly class HmacJwt
{
    /**
     * @param array<string, mixed> $claims
     */
    public static function sign(array $claims, string $key): string
    {
        $signing = self::encode(['alg' => 'HS256', 'typ' => 'JWT']).'.'.self::encode($claims);

        return $signing.'.'.self::base64(hash_hmac('sha256', $signing, $key, true));
    }

    /**
     * The claims of a token, or null when it is malformed, signed with another key or expired.
     *
     * @return array<string, mixed>|null
     */
    public static function claims(string $token, string $key, \DateTimeImmutable $now): ?array
    {
        $parts = explode('.', $token);
        if (3 !== \count($parts)) {
            return null;
        }

        [$header, $payload, $signature] = $parts;
        if (!hash_equals(self::base64(hash_hmac('sha256', $header.'.'.$payload, $key, true)), $signature)) {
            return null;
        }

        $claims = json_decode((string) base64_decode(strtr($payload, '-_', '+/'), true), true);
        if (!\is_array($claims)) {
            return null;
        }

        $expires = $claims['exp'] ?? null;
        if (\is_int($expires) && $expires <= $now->getTimestamp()) {
            return null;
        }

        $typed = [];
        foreach ($claims as $name => $value) {
            $typed[(string) $name] = $value;
        }

        return $typed;
    }

    /**
     * @param array<string, mixed> $part
     */
    private static function encode(array $part): string
    {
        return self::base64(json_encode($part, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES));
    }

    private static function base64(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
