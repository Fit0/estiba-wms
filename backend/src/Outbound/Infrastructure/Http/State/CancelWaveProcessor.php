<?php

declare(strict_types=1);

namespace App\Outbound\Infrastructure\Http\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Outbound\Application\CancelWave\CancelWave;
use App\Outbound\Infrastructure\Http\Resource\WaveResource;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Infrastructure\Http\UriVariable;

/**
 * @implements ProcessorInterface<null, WaveResource>
 */
final readonly class CancelWaveProcessor implements ProcessorInterface
{
    public function __construct(private CommandBus $commandBus)
    {
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): WaveResource
    {
        return WaveResource::fromDomain($this->commandBus->dispatch(new CancelWave(UriVariable::string($uriVariables, 'id'))));
    }
}
