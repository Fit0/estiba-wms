<?php

declare(strict_types=1);

namespace App\Tests\Unit\Erp\Infrastructure\Persistence;

use App\Erp\Application\ShipmentConfirmation;
use App\Erp\Application\ShippedLine;
use App\Erp\Infrastructure\Persistence\ConfirmationPayload;
use PHPUnit\Framework\TestCase;

final class ConfirmationPayloadTest extends TestCase
{
    public function testWhatIsWrittenIsWhatIsRead(): void
    {
        $shippedAt = new \DateTimeImmutable('2026-11-09 11:30:00', new \DateTimeZone('Europe/Madrid'));
        $confirmation = new ShipmentConfirmation('P2026000004', $shippedAt, 'SEUR', 'SE0042261187', 2, [new ShippedLine('PV27-CAM-LIN-AZU-M', 5), new ShippedLine('PV27-PAN-CHI-ARE-42', 6)]);

        $read = ConfirmationPayload::decode(ConfirmationPayload::encode($confirmation));

        self::assertEquals($confirmation, $read);
        self::assertSame('2026-11-09T11:30:00+01:00', $read->shippedAt->format('c'));
    }

    public function testTheTrackingNumberMayBeMissing(): void
    {
        $confirmation = new ShipmentConfirmation('P1', new \DateTimeImmutable('2026-11-09T10:00:00+00:00'), 'Correos', null, 1, [new ShippedLine('X', 1)]);

        self::assertNull(ConfirmationPayload::decode(ConfirmationPayload::encode($confirmation))->trackingNumber);
    }

    public function testRefusesTextThatIsNotAConfirmation(): void
    {
        $this->expectException(\UnexpectedValueException::class);

        ConfirmationPayload::decode('{"orderReference":"P1"}');
    }
}
