<?php

declare(strict_types=1);

namespace App\Inbound\Application\ListAsns;

use App\Inbound\Domain\Asn;
use App\Inbound\Domain\AsnStatus;
use App\Shared\Application\Bus\Query;
use App\Shared\Domain\Page;
use App\Shared\Domain\PageRequest;

/**
 * @implements Query<Page<Asn>>
 */
final readonly class ListAsns implements Query
{
    public function __construct(
        public PageRequest $page,
        public ?AsnStatus $status = null,
    ) {
    }
}
