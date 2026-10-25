<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Application\IssuedRefreshToken;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;

/**
 * The cookie that carries the refresh token (ADR 0022). JavaScript cannot read it (`HttpOnly`), the
 * browser sends it only to the sign-in routes (`Path`) and only from our own site (`SameSite`).
 */
final readonly class RefreshCookie
{
    public const string NAME = 'estiba_refresh';

    private const string PATH = '/api/auth';

    public function issue(IssuedRefreshToken $token, Request $request): Cookie
    {
        return Cookie::create(self::NAME, $token->secret, $token->expiresAt, self::PATH, null, $request->isSecure(), true, false, Cookie::SAMESITE_STRICT);
    }

    public function clear(Request $request): Cookie
    {
        return Cookie::create(self::NAME, '', 1, self::PATH, null, $request->isSecure(), true, false, Cookie::SAMESITE_STRICT);
    }

    /**
     * The secret the browser sent, or an empty string when there is none.
     */
    public function secretOf(Request $request): string
    {
        $value = $request->cookies->get(self::NAME);

        return \is_string($value) ? $value : '';
    }
}
