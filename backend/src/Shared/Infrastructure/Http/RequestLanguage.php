<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use App\Shared\Domain\Language;
use Symfony\Component\HttpFoundation\Request;

/**
 * Picks the language of a response from the `Accept-Language` header (ADR 0004).
 *
 * Spanish is the default: it answers a missing header and any language the API does not
 * speak. A regional variant such as `en-GB` falls back to its language.
 */
final readonly class RequestLanguage
{
    public function of(Request $request): string
    {
        $supported = array_map(static fn (Language $language): string => $language->value, Language::cases());

        return $request->getPreferredLanguage($supported) ?? Language::DEFAULT->value;
    }
}
