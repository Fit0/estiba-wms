<?php

declare(strict_types=1);

namespace App\Outbound\Infrastructure\Http\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Outbound\Application\ReleaseWave\ReleaseWave;
use App\Outbound\Infrastructure\Http\Resource\WaveResource;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Infrastructure\Http\UriVariable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * The body is optional. `{"pickLists": 2}` shares the wave among two operators, and without a body
 * it is one list. A body that is not that is unreadable (400).
 *
 * @implements ProcessorInterface<null, WaveResource>
 */
final readonly class ReleaseWaveProcessor implements ProcessorInterface
{
    public function __construct(private CommandBus $commandBus)
    {
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): WaveResource
    {
        $request = $context['request'] ?? null;
        \assert($request instanceof Request);

        return WaveResource::fromDomain($this->commandBus->dispatch(new ReleaseWave(UriVariable::string($uriVariables, 'id'), $this->pickLists($request))));
    }

    private function pickLists(Request $request): int
    {
        if ('' === trim($request->getContent())) {
            return 1;
        }

        try {
            $body = $request->toArray();
        } catch (\Throwable) {
            throw new BadRequestHttpException('The body must be a JSON object, such as {"pickLists": 2}.');
        }

        $pickLists = $body['pickLists'] ?? 1;
        if (!\is_int($pickLists)) {
            throw new BadRequestHttpException('"pickLists" must be a whole number.');
        }

        return $pickLists;
    }
}
