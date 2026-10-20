<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Builds the one error response every part of the API uses: RFC 9457 `application/problem+json`.
 *
 * `code` and `parameters` never change and carry no language. `title` and `detail` are
 * translated into the language the request asks for (ADR 0004).
 */
final readonly class ProblemFactory
{
    private const string DOMAIN = 'errors';

    public function __construct(
        private TranslatorInterface $translator,
        private RequestLanguage $language,
    ) {
    }

    /**
     * @param array<string, bool|float|int|string> $parameters
     * @param string|null                          $fallbackDetail English text to use if the code has no translation yet
     */
    public function create(Request $request, int $status, string $code, array $parameters = [], ?string $fallbackDetail = null): JsonResponse
    {
        $locale = $this->language->of($request);

        $detail = $this->translator->trans($code, $this->icuArguments($parameters), self::DOMAIN, $locale);
        if ($detail === $code && null !== $fallbackDetail) {
            $detail = $fallbackDetail;
        }

        $response = new JsonResponse([
            'type' => 'urn:estiba:error:'.$code,
            'title' => $this->title($status, $locale),
            'status' => $status,
            'code' => $code,
            'detail' => $detail,
            'parameters' => (object) $parameters,
        ], $status);
        $response->setEncodingOptions(\JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        $response->headers->set('Content-Type', 'application/problem+json');
        $response->headers->set('Content-Language', $locale);
        $response->setVary('Accept-Language', false);

        return $response;
    }

    private function title(int $status, string $locale): string
    {
        $key = 'status.'.$status;
        $title = $this->translator->trans($key, [], self::DOMAIN, $locale);

        return $title === $key ? $this->translator->trans('status.other', [], self::DOMAIN, $locale) : $title;
    }

    /**
     * ICU selects on text, so a boolean becomes the word `true` or `false`.
     *
     * @param array<string, bool|float|int|string> $parameters
     *
     * @return array<string, float|int|string>
     */
    private function icuArguments(array $parameters): array
    {
        return array_map(static fn (bool|float|int|string $value): float|int|string => \is_bool($value) ? ($value ? 'true' : 'false') : $value, $parameters);
    }
}
