<?php

declare(strict_types=1);

namespace App\Outbound\Application\GetWave;

use App\Outbound\Application\WaveLookup;
use App\Outbound\Domain\Exception\WaveNotFound;
use App\Outbound\Domain\Wave;
use App\Shared\Application\Bus\QueryHandler;

final readonly class GetWaveHandler implements QueryHandler
{
    public function __construct(private WaveLookup $lookup)
    {
    }

    /**
     * @throws WaveNotFound
     */
    public function __invoke(GetWave $query): Wave
    {
        return $this->lookup->get($query->waveId);
    }
}
