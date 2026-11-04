<?php

declare(strict_types=1);

namespace App\Analytics\Infrastructure\Http\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Analytics\Application\GetKpis\GetKpis;
use App\Analytics\Infrastructure\Http\Resource\KpiResource;
use App\Shared\Application\Bus\QueryBus;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Reads the figures of the tenant. The optional filters `from` (included) and `until` (not included)
 * are dates with time in ISO 8601, or plain dates that mean midnight UTC. Without them the period is
 * the last seven days.
 *
 * @implements ProviderInterface<KpiResource>
 */
final readonly class KpiProvider implements ProviderInterface
{
    public function __construct(private QueryBus $queryBus)
    {
    }

    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): KpiResource
    {
        $request = $context['request'] ?? null;
        \assert($request instanceof Request);

        return KpiResource::fromReport($this->queryBus->ask(new GetKpis(
            $this->moment($request, 'from'),
            $this->moment($request, 'until'),
        )));
    }

    private function moment(Request $request, string $name): ?\DateTimeImmutable
    {
        if (!$request->query->has($name)) {
            return null;
        }

        $text = $request->query->getString($name);
        $moment = 1 === preg_match('/^\d{4}-\d{2}-\d{2}$/', $text)
            ? \DateTimeImmutable::createFromFormat('!Y-m-d', $text, new \DateTimeZone('UTC'))
            : \DateTimeImmutable::createFromFormat(\DATE_ATOM, $text);

        return false === $moment
            ? throw new BadRequestHttpException(\sprintf('The "%s" filter must be a date, such as 2026-10-24, or a date with time in ISO 8601, such as 2026-10-24T08:00:00+00:00.', $name)) : $moment;
    }
}
