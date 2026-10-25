<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Messenger;

use App\Shared\Domain\AggregateRoot;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

/**
 * Catches a use case that changes an aggregate and forgets to publish what the aggregate raised. An
 * event that nobody publishes is lost to the policies and to the audit trail (ADR 0018), and nothing
 * else would ever notice. It runs only in development and in the tests (config/packages/messenger.yaml),
 * inside the transaction of every command, once its handler has run.
 */
final readonly class PendingEventsGuard implements MiddlewareInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    #[\Override]
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $envelope = $stack->next()->handle($envelope, $stack);

        $forgotten = [];
        foreach ($this->entityManager->getUnitOfWork()->getIdentityMap() as $entities) {
            foreach ($entities as $entity) {
                if ($entity instanceof AggregateRoot && $entity->hasPendingEvents()) {
                    $forgotten[] = $entity::class.' raised '.implode(', ', array_map(static fn (string $event): string => substr((string) strrchr($event, '\\'), 1), $entity->pendingEventClasses()));
                }
            }
        }

        if ([] !== $forgotten) {
            throw new \LogicException(\sprintf('%s handled without publishing the events its aggregates raised: %s. Publish them with EventBus::publish(...$aggregate->pullEvents()) (ADR 0018).', $envelope->getMessage()::class, implode('; ', $forgotten)));
        }

        return $envelope;
    }
}
