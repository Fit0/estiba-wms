<?php

declare(strict_types=1);

namespace App\Tests\Unit\Audit\Application;

use App\Audit\Application\EventDescriber;
use App\Identity\Domain\Email;
use App\Identity\Domain\Event\UserRegistered;
use App\Identity\Domain\UserId;
use App\Outbound\Domain\Event\ParcelClosed;
use App\Outbound\Domain\Event\PickListStarted;
use App\Outbound\Domain\Event\ShortPickReported;
use App\Outbound\Domain\OperatorId;
use App\Outbound\Domain\OrderId;
use App\Outbound\Domain\PickListId;
use App\Outbound\Domain\ShipmentId;
use App\Outbound\Domain\Sscc;
use App\Shared\Domain\DomainEvent;
use App\Shared\Domain\Sensitive;
use App\Shared\Domain\TenantId;
use App\Tests\Unit\Support\Ids;
use PHPUnit\Framework\TestCase;

/**
 * An event told in plain values (AUD-02), whatever the event is.
 */
final class EventDescriberTest extends TestCase
{
    private EventDescriber $describer;

    #[\Override]
    protected function setUp(): void
    {
        $this->describer = new EventDescriber();
    }

    public function testNamesTheEventAfterItsContextAndItsClass(): void
    {
        $tenant = TenantId::fromString(Ids::next());
        $started = new PickListStarted(PickListId::fromString(Ids::next()), OperatorId::fromString(Ids::next()), $tenant, new \DateTimeImmutable('2026-10-24T08:00:00+00:00'));

        self::assertSame('outbound.pick_list_started', $this->describer->describe($started)->type);
        self::assertSame('identity.user_registered', $this->describer->describe($this->userRegistered())->type);
    }

    public function testASubjectIsTheFirstIdentifierOfTheEvent(): void
    {
        $list = PickListId::fromString(Ids::next());
        $started = new PickListStarted($list, OperatorId::fromString(Ids::next()), TenantId::fromString(Ids::next()), new \DateTimeImmutable('2026-10-24T08:00:00+00:00'));

        self::assertSame($list->value, $this->describer->describe($started)->subjectId);
    }

    public function testTheTenantOfTheEventIsTheOneItCarries(): void
    {
        $tenant = TenantId::fromString(Ids::next());
        $started = new PickListStarted(PickListId::fromString(Ids::next()), OperatorId::fromString(Ids::next()), $tenant, new \DateTimeImmutable('2026-10-24T08:00:00+00:00'));

        self::assertSame($tenant->value, $this->describer->describe($started)->tenantId?->value);
    }

    public function testAnEventWithoutATenantHasNone(): void
    {
        $event = new class(PickListId::fromString(Ids::next())) implements DomainEvent {
            public function __construct(public PickListId $pickListId)
            {
            }

            public function occurredAt(): \DateTimeImmutable
            {
                return new \DateTimeImmutable('2026-10-24T08:00:00+00:00');
            }
        };

        self::assertNotInstanceOf(TenantId::class, $this->describer->describe($event)->tenantId);
    }

    public function testIdentifiersNumbersAndTextBecomePlainValues(): void
    {
        $order = OrderId::fromString(Ids::next());
        $list = PickListId::fromString(Ids::next());
        $tenant = TenantId::fromString(Ids::next());
        $short = new ShortPickReported($list, 3, $order, 2, 4, $tenant, new \DateTimeImmutable('2026-10-24T08:00:00+00:00'));

        self::assertSame([
            'pickListId' => $list->value,
            'position' => 3,
            'orderId' => $order->value,
            'orderLineNumber' => 2,
            'missingUnits' => 4,
            'tenantId' => $tenant->value,
        ], $this->describer->describe($short)->data);
    }

    public function testValuesWithATextFormBecomeText(): void
    {
        $closed = new ParcelClosed(ShipmentId::fromString(Ids::next()), TenantId::fromString(Ids::next()), OrderId::fromString(Ids::next()), Sscc::fromString('084000000000000017'), new \DateTimeImmutable('2026-10-24T08:00:00+00:00'));

        self::assertSame('084000000000000017', $this->describer->describe($closed)->data['sscc']);
    }

    public function testEnumsDatesAndListsBecomePlainValues(): void
    {
        $event = new class implements DomainEvent {
            public Status $status = Status::Open;

            public \DateTimeImmutable $at;

            /** @var list<Status> */
            public array $history = [Status::Open, Status::Closed];

            public ?string $note = null;

            public function __construct()
            {
                $this->at = new \DateTimeImmutable('2026-10-24T08:00:00+00:00');
            }

            public function occurredAt(): \DateTimeImmutable
            {
                return $this->at;
            }
        };

        self::assertSame([
            'status' => 'open',
            'at' => '2026-10-24T08:00:00+00:00',
            'history' => ['open', 'closed'],
            'note' => null,
        ], $this->describer->describe($event)->data);
    }

    public function testAValueObjectWithoutATextFormIsToldByItsFields(): void
    {
        $event = new class implements DomainEvent {
            public Dimensions $box;

            public function __construct()
            {
                $this->box = new Dimensions(300, 200);
            }

            public function occurredAt(): \DateTimeImmutable
            {
                return new \DateTimeImmutable('2026-10-24T08:00:00+00:00');
            }
        };

        self::assertSame(['box' => ['length' => 300, 'width' => 200]], $this->describer->describe($event)->data);
    }

    // AUD-03
    public function testPersonalDataMarkedSensitiveStaysOutOfTheTrail(): void
    {
        $description = $this->describer->describe($this->userRegistered());

        self::assertArrayNotHasKey('email', $description->data);
        self::assertArrayHasKey('userId', $description->data);
        self::assertArrayHasKey('tenantId', $description->data);
        self::assertStringNotContainsString('@', (string) json_encode($description->data));
    }

    public function testSensitiveDataStaysOutOfNestedObjectsToo(): void
    {
        $event = new class implements DomainEvent {
            public Contact $contact;

            public function __construct()
            {
                $this->contact = new Contact('Marea Moda', 'ops@example.test');
            }

            public function occurredAt(): \DateTimeImmutable
            {
                return new \DateTimeImmutable('2026-10-24T08:00:00+00:00');
            }
        };

        self::assertSame(['contact' => ['name' => 'Marea Moda']], $this->describer->describe($event)->data);
    }

    public function testAClassOutsideTheLayoutKeepsItsFullName(): void
    {
        $event = new class implements DomainEvent {
            public function occurredAt(): \DateTimeImmutable
            {
                return new \DateTimeImmutable('2026-10-24T08:00:00+00:00');
            }
        };

        self::assertSame($event::class, $this->describer->describe($event)->type);
    }

    private function userRegistered(): UserRegistered
    {
        return new UserRegistered(UserId::fromString(Ids::next()), TenantId::fromString(Ids::next()), Email::fromString('ana@example.test'), new \DateTimeImmutable('2026-10-24T08:00:00+00:00'));
    }
}

enum Status: string
{
    case Open = 'open';
    case Closed = 'closed';
}

final readonly class Dimensions
{
    public function __construct(
        public int $length,
        public int $width,
    ) {
    }
}

final readonly class Contact
{
    public function __construct(
        public string $name,
        #[Sensitive]
        public string $email,
    ) {
    }
}
