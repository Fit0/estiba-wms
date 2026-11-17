<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Adds the security headers to every answer of the API (E10-05, ADR 0053).
 *
 * The API sends JSON and never a page a browser should draw, so its policy is the strictest one: no
 * content may load from an answer of the API and no page may frame it. The documentation page under
 * `/api/docs` is the exception, since it is a page with its own scripts and styles, and it gets every
 * header but the content policy. The headers an answer already carries are left as they are.
 *
 * `Strict-Transport-Security` is only sent over HTTPS, because a browser ignores it over HTTP and a
 * development server would otherwise tell it to insist on HTTPS for a year.
 */
#[AsEventListener(event: KernelEvents::RESPONSE)]
final readonly class SecurityHeadersListener
{
    /** @var array<string, string> */
    public const array HEADERS = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'Referrer-Policy' => 'no-referrer',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
        'Cross-Origin-Resource-Policy' => 'same-origin',
    ];

    public const string CONTENT_SECURITY_POLICY = "default-src 'none'; frame-ancestors 'none'";

    public const string STRICT_TRANSPORT_SECURITY = 'max-age=31536000; includeSubDomains';

    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();

        foreach (self::HEADERS as $name => $value) {
            $this->setUnlessPresent($response, $name, $value);
        }

        if ($this->isApiAnswer($request)) {
            $this->setUnlessPresent($response, 'Content-Security-Policy', self::CONTENT_SECURITY_POLICY);
        }

        if ($request->isSecure()) {
            $this->setUnlessPresent($response, 'Strict-Transport-Security', self::STRICT_TRANSPORT_SECURITY);
        }
    }

    private function isApiAnswer(Request $request): bool
    {
        $path = $request->getPathInfo();

        if (str_starts_with($path, '/api/docs')) {
            return false;
        }

        return str_starts_with($path, '/api') || str_starts_with($path, '/health');
    }

    private function setUnlessPresent(Response $response, string $name, string $value): void
    {
        if (!$response->headers->has($name)) {
            $response->headers->set($name, $value);
        }
    }
}
