<?php

declare(strict_types=1);

namespace App\Tests\Unit\Support;

use App\Shared\Infrastructure\Http\HttpProblem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Yaml\Yaml;

/**
 * Reads the error codes the code can raise and the translation files, so tests can compare them.
 */
final class ErrorCatalog
{
    public const array LANGUAGES = ['es', 'en'];

    private const string DOMAIN = 'errors+intl-icu';

    /**
     * Every error code the API can answer with, and the parameters each one carries.
     *
     * Domain codes are read from the source: a domain exception builds itself with
     * `new self('code', ['parameter' => $value], 'message')`.
     *
     * @return array<string, list<string>>
     */
    public static function declaredErrors(): array
    {
        $errors = [];

        $files = new Finder()->files()->in(self::backendPath().'/src')->name('*.php')->contains('extends DomainRuleViolation');
        foreach ($files as $file) {
            preg_match_all("/new self\\(\\s*'([a-z0-9_]+\\.[a-z0-9_]+)',\\s*(\\[.*?\\]),/s", $file->getContents(), $matches, \PREG_SET_ORDER);
            foreach ($matches as $match) {
                preg_match_all("/'(\\w+)'\\s*=>/", $match[2], $names);
                $errors[$match[1]] = $names[1];
            }
        }

        foreach (HttpProblem::cases() as $problem) {
            $errors[$problem->value] = [];
        }

        ksort($errors);

        return $errors;
    }

    /**
     * Every key of a language's catalogue, as the dotted path Symfony uses.
     *
     * @return array<string, string>
     */
    public static function messages(string $language): array
    {
        $parsed = Yaml::parseFile(self::file($language));
        \assert(\is_array($parsed));

        $flat = [];
        self::flatten($parsed, '', $flat);

        return $flat;
    }

    /**
     * A translator loaded with the real catalogues, without booting the kernel.
     */
    public static function translator(string $locale = 'es'): Translator
    {
        $translator = new Translator($locale);
        $translator->setFallbackLocales(['es']);
        $translator->addLoader('yaml', new YamlFileLoader());
        foreach (self::LANGUAGES as $language) {
            $translator->addResource('yaml', self::file($language), $language, self::DOMAIN);
        }

        return $translator;
    }

    /**
     * The names of the arguments an ICU message uses, looking inside every select branch.
     *
     * @return list<string>
     */
    public static function placeholders(string $message): array
    {
        $names = [];
        $length = \strlen($message);

        for ($position = 0; $position < $length; ++$position) {
            if ('{' !== $message[$position]) {
                continue;
            }

            $end = self::closingBrace($message, $position);
            $inner = substr($message, $position + 1, $end - $position - 1);
            $comma = strpos($inner, ',');

            if (false === $comma) {
                $names[] = trim($inner);
            } else {
                $names[] = trim(substr($inner, 0, $comma));
                foreach (self::branchBodies(substr($inner, $comma + 1)) as $body) {
                    array_push($names, ...self::placeholders($body));
                }
            }

            $position = $end;
        }

        $names = array_values(array_unique($names));
        sort($names);

        return $names;
    }

    private static function file(string $language): string
    {
        return \sprintf('%s/translations/errors+intl-icu.%s.yaml', self::backendPath(), $language);
    }

    private static function backendPath(): string
    {
        return \dirname(__DIR__, 3);
    }

    /**
     * @param array<array-key, mixed> $tree
     * @param array<string, string>   $flat
     */
    private static function flatten(array $tree, string $prefix, array &$flat): void
    {
        foreach ($tree as $key => $value) {
            if (\is_array($value)) {
                self::flatten($value, $prefix.$key.'.', $flat);
            } else {
                \assert(\is_string($value));
                $flat[$prefix.$key] = $value;
            }
        }
    }

    /**
     * @return list<string> the text inside each top-level {...} of a "select, key {body} key {body}" tail
     */
    private static function branchBodies(string $tail): array
    {
        $bodies = [];
        $length = \strlen($tail);

        for ($position = 0; $position < $length; ++$position) {
            if ('{' === $tail[$position]) {
                $end = self::closingBrace($tail, $position);
                $bodies[] = substr($tail, $position + 1, $end - $position - 1);
                $position = $end;
            }
        }

        return $bodies;
    }

    private static function closingBrace(string $text, int $open): int
    {
        $depth = 0;
        $length = \strlen($text);

        for ($position = $open; $position < $length; ++$position) {
            if ('{' === $text[$position]) {
                ++$depth;
            } elseif ('}' === $text[$position] && 0 === --$depth) {
                return $position;
            }
        }

        throw new \LogicException('Unbalanced braces in the ICU message: '.$text);
    }
}
