<?php

declare(strict_types=1);

namespace App\Identity\Domain;

/**
 * What a refresh token that someone presents can still do.
 */
enum RefreshTokenStatus
{
    /** It can be exchanged for a new one. */
    case Usable;

    /** It lived past its expiry date or past the end of its session. */
    case Expired;

    /** It was revoked, by signing out or because its family was. */
    case Revoked;

    /** It was exchanged a moment ago: two tabs asking at once. Refused, but nothing else is touched. */
    case JustRotated;

    /** It was exchanged long ago and shows up again: someone kept a copy of it. */
    case Reused;
}
