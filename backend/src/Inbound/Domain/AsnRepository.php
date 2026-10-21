<?php

declare(strict_types=1);

namespace App\Inbound\Domain;

use App\Shared\Domain\Page;
use App\Shared\Domain\PageRequest;
use App\Shared\Domain\TenantId;

/**
 * There is no delete method: an ASN that will not arrive is cancelled, and its history stays.
 */
interface AsnRepository extends AsnReferenceRegistry
{
    public function find(AsnId $id): ?Asn;

    /**
     * The tenant's ASNs, the latest expected date first, optionally narrowed to one status.
     *
     * @return Page<Asn>
     */
    public function search(TenantId $tenantId, ?AsnStatus $status, PageRequest $page): Page;

    public function save(Asn $asn): void;
}
