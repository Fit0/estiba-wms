<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use Symfony\Component\HttpFoundation\Response;

/**
 * Error codes for problems that are not a broken domain rule: the request itself is wrong, the
 * caller is not who it must be, or something unexpected failed. Each has a translation in `translations/errors+intl-icu.*.yaml`.
 */
enum HttpProblem: string
{
    case Unauthenticated = 'shared.unauthenticated';
    case TokenInvalid = 'shared.token_invalid';
    case TokenExpired = 'shared.token_expired';
    case CredentialsInvalid = 'identity.credentials_invalid';
    case RefreshTokenInvalid = 'identity.refresh_token_invalid';
    case Forbidden = 'shared.forbidden';
    case RequestMalformed = 'shared.request_malformed';
    case NotFound = 'shared.not_found';
    case MethodNotAllowed = 'shared.method_not_allowed';
    case NotAcceptable = 'shared.not_acceptable';
    case UnsupportedMediaType = 'shared.unsupported_media_type';
    case RequestInvalid = 'shared.request_invalid';
    case ConcurrentUpdate = 'shared.concurrent_update';
    case InternalError = 'shared.internal_error';

    /**
     * The problem that describes a response with this HTTP status.
     */
    public static function forStatus(int $status): self
    {
        return match (true) {
            Response::HTTP_BAD_REQUEST === $status => self::RequestMalformed,
            Response::HTTP_UNAUTHORIZED === $status => self::Unauthenticated,
            Response::HTTP_FORBIDDEN === $status => self::Forbidden,
            Response::HTTP_NOT_FOUND === $status => self::NotFound,
            Response::HTTP_METHOD_NOT_ALLOWED === $status => self::MethodNotAllowed,
            Response::HTTP_NOT_ACCEPTABLE === $status => self::NotAcceptable,
            Response::HTTP_CONFLICT === $status => self::ConcurrentUpdate,
            Response::HTTP_UNSUPPORTED_MEDIA_TYPE === $status => self::UnsupportedMediaType,
            $status >= Response::HTTP_INTERNAL_SERVER_ERROR => self::InternalError,
            default => self::RequestInvalid,
        };
    }
}
