<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use Symfony\Component\HttpFoundation\Request;

/**
 * Picks the language of a response from the `Accept-Language` header (ADR 0004).
 *
 * Spanish is the default: it answers a missing header and any language the API does not
 * speak. A regional variant such as `en-GB` falls back to its language.
 */
final readonly class RequestLanguage
{
    public const string DEFAULT = 'es';

    /** @var list<string> */
    public const array SUPPORTED = ['es', 'en'];

    public function of(Request $request): string
    {
        return $request->getPreferredLanguage(self::SUPPORTED) ?? self::DEFAULT;
    }
}
