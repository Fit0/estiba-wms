<?php

declare(strict_types=1);

namespace App\Inbound\Application;

use App\Inbound\Domain\Asn;
use App\Inbound\Domain\AsnId;
use App\Inbound\Domain\AsnRepository;
use App\Inbound\Domain\Exception\AsnNotFound;
use App\Shared\Application\CurrentTenant;

/**
 * Finds an ASN of the current tenant. An ASN of another tenant is reported as not found.
 */
final readonly class AsnLookup
{
    public function __construct(
        private AsnRepository $asns,
        private CurrentTenant $tenant,
    ) {
    }

    /**
     * @throws AsnNotFound
     */
    public function get(string $id): Asn
    {
        $asn = $this->asns->find(AsnId::fromString($id));

        if (!$asn instanceof Asn || !$asn->tenantId()->equals($this->tenant->id())) {
            throw AsnNotFound::forId($id);
        }

        return $asn;
    }
}
