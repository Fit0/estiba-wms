<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Http;

use App\Tests\Unit\Support\ErrorCatalog;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The contract behind ADR 0004: an error code never reaches the API without a text in every
 * language, and a text never refers to a parameter the error does not carry.
 */
final class ErrorCatalogTest extends TestCase
{
    public function testFindsTheErrorsTheCodeCanRaise(): void
    {
        $errors = ErrorCatalog::declaredErrors();

        // A guard for the scan itself: if it stops finding codes, the other tests prove nothing.
        self::assertGreaterThan(35, \count($errors));
        self::assertSame(['value'], $errors['warehouse.location_code_invalid']);
        self::assertSame(['field', 'value', 'trackedByExpiry'], $errors['catalog.shelf_life_invalid']);
        self::assertSame([], $errors['shared.not_found']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function languages(): iterable
    {
        foreach (ErrorCatalog::LANGUAGES as $language) {
            yield $language => [$language];
        }
    }

    #[DataProvider('languages')]
    public function testEveryErrorCodeHasATextInEveryLanguage(string $language): void
    {
        $missing = array_diff(array_keys(ErrorCatalog::declaredErrors()), array_keys(ErrorCatalog::messages($language)));

        self::assertSame([], array_values($missing), \sprintf('Add these codes to translations/errors+intl-icu.%s.yaml.', $language));
    }

    #[DataProvider('languages')]
    public function testNoTextIsLeftForACodeThatNoLongerExists(string $language): void
    {
        $keys = array_filter(array_keys(ErrorCatalog::messages($language)), static fn (string $key): bool => !str_starts_with($key, 'status.'));
        $leftOver = array_diff($keys, array_keys(ErrorCatalog::declaredErrors()));

        self::assertSame([], array_values($leftOver), \sprintf('Remove these keys from translations/errors+intl-icu.%s.yaml.', $language));
    }

    public function testBothLanguagesHaveTheSameKeys(): void
    {
        $spanish = array_keys(ErrorCatalog::messages('es'));
        $english = array_keys(ErrorCatalog::messages('en'));

        self::assertSame([], array_values(array_diff($spanish, $english)), 'Keys only in Spanish.');
        self::assertSame([], array_values(array_diff($english, $spanish)), 'Keys only in English.');
    }

    public function testEveryTitleStatusHasAnEntryAndAFallbackExists(): void
    {
        foreach (ErrorCatalog::LANGUAGES as $language) {
            $messages = ErrorCatalog::messages($language);

            self::assertArrayHasKey('status.other', $messages);
            foreach ([400, 401, 404, 405, 406, 409, 415, 422, 500] as $status) {
                self::assertArrayHasKey('status.'.$status, $messages, \sprintf('%s has no title for HTTP %d.', $language, $status));
            }
        }
    }

    #[DataProvider('languages')]
    public function testATextOnlyUsesParametersTheErrorCarries(string $language): void
    {
        $messages = ErrorCatalog::messages($language);

        foreach (ErrorCatalog::declaredErrors() as $code => $parameters) {
            $unknown = array_diff(ErrorCatalog::placeholders($messages[$code] ?? ''), $parameters);

            self::assertSame([], array_values($unknown), \sprintf('%s [%s] uses parameters the error does not carry.', $code, $language));
        }
    }

    public function testBothLanguagesUseTheSameParameters(): void
    {
        $spanish = ErrorCatalog::messages('es');
        $english = ErrorCatalog::messages('en');

        foreach (array_keys(ErrorCatalog::declaredErrors()) as $code) {
            self::assertSame(
                ErrorCatalog::placeholders($spanish[$code] ?? ''),
                ErrorCatalog::placeholders($english[$code] ?? ''),
                \sprintf('%s mentions different parameters in Spanish and in English.', $code),
            );
        }
    }

    #[DataProvider('languages')]
    public function testEveryTextRendersWithItsParameters(string $language): void
    {
        $messages = ErrorCatalog::messages($language);

        foreach (ErrorCatalog::declaredErrors() as $code => $parameters) {
            $arguments = array_fill_keys($parameters, 'sample');
            $text = \MessageFormatter::formatMessage($language, $messages[$code] ?? '', $arguments);

            self::assertIsString($text, \sprintf('%s [%s] does not render: %s', $code, $language, intl_get_error_message()));
            self::assertStringNotContainsString('{', $text, \sprintf('%s [%s] left a placeholder unfilled.', $code, $language));
            self::assertNotSame('', trim($text));
        }
    }

    public function testChoosesTheWordingFromTheParameter(): void
    {
        $translator = ErrorCatalog::translator();
        $arguments = ['field' => 'minShelfLifeAtReceiptDays', 'value' => -3];

        self::assertSame(
            'La vida útil mínima en la recepción debe ser de cero o más días, y se ha recibido -3.',
            $translator->trans('catalog.shelf_life_invalid', $arguments + ['trackedByExpiry' => 'true'], 'errors', 'es'),
        );
        self::assertSame(
            'The minimum shelf life at receipt can only be set on products tracked by expiry date.',
            $translator->trans('catalog.shelf_life_invalid', $arguments + ['trackedByExpiry' => 'false'], 'errors', 'en'),
        );
    }
}
