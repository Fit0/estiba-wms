<?php

declare(strict_types=1);

namespace App\Inbound\Application\GetAsn;

use App\Inbound\Application\AsnLookup;
use App\Inbound\Domain\Asn;
use App\Inbound\Domain\Exception\AsnNotFound;
use App\Shared\Application\Bus\QueryHandler;

final readonly class GetAsnHandler implements QueryHandler
{
    public function __construct(private AsnLookup $lookup)
    {
    }

    /**
     * @throws AsnNotFound
     */
    public function __invoke(GetAsn $query): Asn
    {
        return $this->lookup->get($query->asnId);
    }
}
