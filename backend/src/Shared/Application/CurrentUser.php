<?php

declare(strict_types=1);

namespace App\Shared\Application;

/**
 * The user on whose behalf the current request acts, for the records that say who did something,
 * such as the author of a stock movement. Only the identifier is given: users live in Identity.
 */
interface CurrentUser
{
    /**
     * @throws TenantNotResolved when nobody is signed in
     */
    public function id(): string;
}
