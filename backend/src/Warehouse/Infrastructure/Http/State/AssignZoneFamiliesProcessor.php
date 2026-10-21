<?php

declare(strict_types=1);

namespace App\Warehouse\Infrastructure\Http\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Infrastructure\Http\UriVariable;
use App\Warehouse\Application\AssignZoneFamilies\AssignZoneFamilies;
use App\Warehouse\Domain\ZoneId;
use App\Warehouse\Infrastructure\Http\Input\AssignZoneFamiliesInput;
use App\Warehouse\Infrastructure\Http\Resource\ZoneResource;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @implements ProcessorInterface<AssignZoneFamiliesInput, ZoneResource>
 */
final readonly class AssignZoneFamiliesProcessor implements ProcessorInterface
{
    public function __construct(private CommandBus $commandBus)
    {
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ZoneResource
    {
        $zoneId = UriVariable::string($uriVariables, 'id');
        $warehouse = $this->commandBus->dispatch(new AssignZoneFamilies($zoneId, $this->families($data->families)));

        return ZoneResource::fromDomain($warehouse->id(), $warehouse->zoneWithId(ZoneId::fromString($zoneId)));
    }

    /**
     * @param array<array-key, mixed> $families
     *
     * @return list<string>
     */
    private function families(array $families): array
    {
        $read = [];
        foreach ($families as $family) {
            if (!\is_string($family)) {
                throw new BadRequestHttpException('Each family must be a text.');
            }

            $read[] = $family;
        }

        return $read;
    }
}
