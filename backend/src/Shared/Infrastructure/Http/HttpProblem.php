<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use Symfony\Component\HttpFoundation\Response;

/**
 * Error codes for problems that are not a broken domain rule: the request itself is wrong,
 * or something unexpected failed. Each has a translation in `translations/errors+intl-icu.*.yaml`.
 */
enum HttpProblem: string
{
    case TenantMissing = 'shared.tenant_missing';
    case RequestMalformed = 'shared.request_malformed';
    case NotFound = 'shared.not_found';
    case MethodNotAllowed = 'shared.method_not_allowed';
    case NotAcceptable = 'shared.not_acceptable';
    case UnsupportedMediaType = 'shared.unsupported_media_type';
    case RequestInvalid = 'shared.request_invalid';
    case InternalError = 'shared.internal_error';

    /**
     * The problem that describes a response with this HTTP status.
     */
    public static function forStatus(int $status): self
    {
        return match (true) {
            Response::HTTP_BAD_REQUEST === $status => self::RequestMalformed,
            Response::HTTP_NOT_FOUND === $status => self::NotFound,
            Response::HTTP_METHOD_NOT_ALLOWED === $status => self::MethodNotAllowed,
            Response::HTTP_NOT_ACCEPTABLE === $status => self::NotAcceptable,
            Response::HTTP_UNSUPPORTED_MEDIA_TYPE === $status => self::UnsupportedMediaType,
            $status >= Response::HTTP_INTERNAL_SERVER_ERROR => self::InternalError,
            default => self::RequestInvalid,
        };
    }
}
