<?php

declare(strict_types=1);

namespace App\Inbound\Application\GetAsn;

use App\Inbound\Domain\Asn;
use App\Shared\Application\Bus\Query;

/**
 * One ASN of the current tenant.
 *
 * @implements Query<Asn>
 */
final readonly class GetAsn implements Query
{
    public function __construct(public string $asnId)
    {
    }
}
