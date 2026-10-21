<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inbound\Domain;

use App\Inbound\Domain\AsnReference;
use App\Inbound\Domain\Event\PutawayConfirmed;
use App\Inbound\Domain\Event\ReceiptClosed;
use App\Inbound\Domain\LotNumber;
use App\Inbound\Domain\ProductId;
use App\Inbound\Domain\PutawayTaskId;
use App\Inbound\Domain\ReceivedGoods;
use App\Shared\Domain\DomainEvent;
use App\Tests\Unit\Support\Ids;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;

/**
 * The events routed to the async transport travel through Redis (ADR 0012): they must come back
 * from it exactly as they left, value objects included.
 */
final class RoutedEventsTest extends TestCase
{
    /**
     * @return iterable<string, array{DomainEvent}>
     */
    public static function routedEvents(): iterable
    {
        yield 'receipt closed' => [new ReceiptClosed(InboundMother::receiptId(), InboundMother::asnId(), InboundMother::operator(), InboundMother::at())];
        yield 'putaway confirmed' => [new PutawayConfirmed(
            PutawayTaskId::fromString(Ids::next()),
            InboundMother::tenantId(),
            new ReceivedGoods(ProductId::fromString(Ids::next()), 12, LotNumber::fromString('L-0425'), new \DateTimeImmutable('2027-03-31'), true),
            InboundMother::dock(),
            InboundMother::dock(),
            AsnReference::fromString('ALB-2027-0153'),
            InboundMother::operator(),
            InboundMother::at(),
        )];
    }

    #[DataProvider('routedEvents')]
    public function testTravelsThroughTheTransportUnchanged(DomainEvent $event): void
    {
        $serializer = new PhpSerializer();

        $decoded = $serializer->decode($serializer->encode(new Envelope($event)))->getMessage();

        self::assertEquals($event, $decoded);
        self::assertEquals($event->occurredAt(), $decoded instanceof DomainEvent ? $decoded->occurredAt() : null);
    }
}
