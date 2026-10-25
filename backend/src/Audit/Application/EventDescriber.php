<?php

declare(strict_types=1);

namespace App\Audit\Application;

use App\Shared\Domain\DomainEvent;
use App\Shared\Domain\Identifier;
use App\Shared\Domain\Sensitive;
use App\Shared\Domain\TenantId;

/**
 * Tells any domain event in plain values, without knowing the event: its name comes from where the
 * class lives, and its data from its public fields. Identifiers, value objects with a text form,
 * dates and enums become text, and a field marked {@see Sensitive} is left out.
 *
 * The subject of the event is its first identifier, which in every event is the aggregate it is
 * about.
 */
final readonly class EventDescriber
{
    private const int MAX_DEPTH = 4;

    public function describe(DomainEvent $event): EventDescription
    {
        $data = [];
        $subjectId = null;
        $tenantId = null;

        foreach (new \ReflectionObject($event)->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            if ([] !== $property->getAttributes(Sensitive::class)) {
                continue;
            }

            $value = $property->getValue($event);
            $data[$property->getName()] = $this->plain($value, 1);
            if ($value instanceof TenantId && 'tenantId' === $property->getName()) {
                $tenantId = $value;
            }
            if (null === $subjectId && $value instanceof Identifier) {
                $subjectId = $value->value;
            }
        }

        return new EventDescription($this->typeOf($event), $subjectId, $tenantId, $data);
    }

    /**
     * `App\Outbound\Domain\Event\PickListStarted` is `outbound.pick_list_started`. A class that does
     * not follow the layout keeps its full name, so it is still told apart.
     */
    private function typeOf(DomainEvent $event): string
    {
        if (1 === preg_match('/^App\\\\(\w+)\\\\Domain\\\\Event\\\\(\w+)$/', $event::class, $parts)) {
            $name = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $parts[2]));

            return strtolower($parts[1]).'.'.$name;
        }

        return $event::class;
    }

    private function plain(mixed $value, int $depth): mixed
    {
        return match (true) {
            null === $value, \is_scalar($value) => $value,
            $value instanceof \BackedEnum => $value->value,
            $value instanceof \DateTimeInterface => $value->format(\DATE_ATOM),
            $value instanceof \Stringable => (string) $value,
            \is_array($value) => $depth > self::MAX_DEPTH ? null : array_map(fn (mixed $item): mixed => $this->plain($item, $depth + 1), $value),
            \is_object($value) => $depth > self::MAX_DEPTH ? null : $this->fields($value, $depth),
            default => null,
        };
    }

    /**
     * The public fields of a value object that has no text form.
     *
     * @return array<string, mixed>
     */
    private function fields(object $value, int $depth): array
    {
        $fields = [];
        foreach (new \ReflectionObject($value)->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            if ([] === $property->getAttributes(Sensitive::class)) {
                $fields[$property->getName()] = $this->plain($property->getValue($value), $depth + 1);
            }
        }

        return $fields;
    }
}
