<?php

declare(strict_types=1);

namespace App\Inbound\Infrastructure\Http\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Inbound\Application\RegisterAsn\RegisterAsn;
use App\Inbound\Infrastructure\Http\Input\RegisterAsnInput;
use App\Inbound\Infrastructure\Http\Input\RegisterAsnLines;
use App\Inbound\Infrastructure\Http\Resource\AsnResource;
use App\Shared\Application\Bus\CommandBus;

/**
 * @implements ProcessorInterface<RegisterAsnInput, AsnResource>
 */
final readonly class RegisterAsnProcessor implements ProcessorInterface
{
    public function __construct(private CommandBus $commandBus)
    {
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AsnResource
    {
        return AsnResource::fromDomain($this->commandBus->dispatch(new RegisterAsn(
            $data->reference,
            $data->supplier,
            $data->expectedDate,
            RegisterAsnLines::from($data->lines),
        )));
    }
}
