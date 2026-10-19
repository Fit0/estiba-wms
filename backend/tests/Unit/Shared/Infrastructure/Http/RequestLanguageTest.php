<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Http;

use App\Shared\Infrastructure\Http\RequestLanguage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class RequestLanguageTest extends TestCase
{
    #[DataProvider('headers')]
    public function testPicksTheLanguageFromAcceptLanguage(?string $header, string $expected): void
    {
        $request = Request::create('/api/warehouses');
        // Request::create adds `Accept-Language: en-us,en;q=0.5` on its own, so "no header" must be removed.
        if (null === $header) {
            $request->headers->remove('Accept-Language');
        } else {
            $request->headers->set('Accept-Language', $header);
        }

        self::assertSame($expected, new RequestLanguage()->of($request));
    }

    /**
     * @return iterable<string, array{string|null, string}>
     */
    public static function headers(): iterable
    {
        yield 'no header means Spanish, the default' => [null, 'es'];
        yield 'Spanish' => ['es', 'es'];
        yield 'English' => ['en', 'en'];
        yield 'a regional variant falls back to its language' => ['en-GB', 'en'];
        yield 'regional English with a quality list' => ['en-GB,en;q=0.9', 'en'];
        yield 'regional Spanish' => ['es-ES,es;q=0.9,en;q=0.8', 'es'];
        yield 'the highest quality wins' => ['es;q=0.5,en;q=0.9', 'en'];
        yield 'an unsupported language is skipped for the next one' => ['fr,en;q=0.5', 'en'];
        yield 'only unsupported languages mean Spanish' => ['fr-FR,fr;q=0.9,de;q=0.8', 'es'];
        yield 'any language means Spanish' => ['*', 'es'];
        yield 'an empty header means Spanish' => ['', 'es'];
    }
}
