<?php

declare(strict_types=1);

namespace App\Outbound\Application;

use App\Outbound\Domain\Exception\WaveNotFound;
use App\Outbound\Domain\Wave;
use App\Outbound\Domain\WaveId;
use App\Outbound\Domain\WaveRepository;
use App\Shared\Application\CurrentTenant;

/**
 * Finds a wave of the current tenant. A wave of another tenant is reported as not found.
 */
final readonly class WaveLookup
{
    public function __construct(
        private WaveRepository $waves,
        private CurrentTenant $tenant,
    ) {
    }

    /**
     * @throws WaveNotFound
     */
    public function get(string $id): Wave
    {
        $wave = $this->waves->find(WaveId::fromString($id));

        if (!$wave instanceof Wave || !$wave->tenantId()->equals($this->tenant->id())) {
            throw WaveNotFound::forId($id);
        }

        return $wave;
    }
}
