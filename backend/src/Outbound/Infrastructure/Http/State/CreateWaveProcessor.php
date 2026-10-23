<?php

declare(strict_types=1);

namespace App\Outbound\Infrastructure\Http\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Outbound\Application\CreateWave\CreateWave;
use App\Outbound\Infrastructure\Http\Input\CreateWaveInput;
use App\Outbound\Infrastructure\Http\Input\CreateWaveOrderIds;
use App\Outbound\Infrastructure\Http\Resource\WaveResource;
use App\Shared\Application\Bus\CommandBus;

/**
 * @implements ProcessorInterface<CreateWaveInput, WaveResource>
 */
final readonly class CreateWaveProcessor implements ProcessorInterface
{
    public function __construct(private CommandBus $commandBus)
    {
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): WaveResource
    {
        return WaveResource::fromDomain($this->commandBus->dispatch(new CreateWave(CreateWaveOrderIds::from($data->orderIds))));
    }
}
