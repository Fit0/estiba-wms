<?php

declare(strict_types=1);

namespace App\Tests\Unit\Erp\Infrastructure\Soap;

use App\Erp\Application\ConfirmationOutcome;
use App\Erp\Application\ShipmentConfirmation;
use App\Erp\Application\ShippedLine;
use App\Erp\Domain\Exception\ErpUnavailable;
use App\Erp\Infrastructure\Soap\SoapClientProvider;
use App\Erp\Infrastructure\Soap\SoapErpGateway;
use App\Tests\Support\FakeErpSoapClient;
use App\Tests\Unit\Support\AssertsDomainErrors;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The gateway turns what the warehouse knows into what the ERP asks, and the ERP's codes into outcomes
 * and exceptions. The client is a stand-in that reads the real WSDL, so a request that the contract would
 * not accept fails here too.
 */
final class SoapErpGatewayTest extends TestCase
{
    use AssertsDomainErrors;

    public function testSendsTheConfirmationInTheWordsOfTheErp(): void
    {
        $client = FakeErpSoapClient::answering(0, 'Envío confirmado');

        $outcome = $this->gatewayWith($client)->confirmShipment($this->confirmation());

        self::assertSame(ConfirmationOutcome::Confirmed, $outcome);
        $request = (string) $client->lastRequest;
        self::assertStringContainsString('<ns1:NumeroPedido>P2026000001</ns1:NumeroPedido>', $request);
        // Spain's day, not the UTC one: 23:30 UTC on the 5th is already the 6th in Madrid.
        self::assertStringContainsString('<ns1:FechaEnvio>06/11/2026</ns1:FechaEnvio>', $request);
        self::assertStringContainsString('<ns1:Transportista>SEUR</ns1:Transportista>', $request);
        self::assertStringContainsString('<ns1:Seguimiento>TRK-99</ns1:Seguimiento>', $request);
        self::assertStringContainsString('<ns1:Bultos>2</ns1:Bultos>', $request);
        self::assertStringContainsString('<ns1:Articulo>PV27-CAM-LIN-BLA-S</ns1:Articulo><ns1:Unidades>3</ns1:Unidades>', $request);
        self::assertStringContainsString('<ns1:Articulo>PV27-PAN-CHI-ARE-40</ns1:Articulo><ns1:Unidades>1</ns1:Unidades>', $request);
    }

    public function testLeavesOutTheTrackingNumberWhenThereIsNone(): void
    {
        $client = FakeErpSoapClient::answering(0, 'Envío confirmado');

        $this->gatewayWith($client)->confirmShipment($this->confirmation(trackingNumber: null));

        self::assertStringNotContainsString('Seguimiento', (string) $client->lastRequest);
    }

    public function testASecondConfirmationIsNotAFailure(): void
    {
        $outcome = $this->gatewayWith(FakeErpSoapClient::answering(1, 'El pedido P2026000001 ya estaba confirmado'))->confirmShipment($this->confirmation());

        self::assertSame(ConfirmationOutcome::AlreadyConfirmed, $outcome);
    }

    public function testAnOrderNotExportedYetCanBeTriedAgain(): void
    {
        self::assertDomainError('erp.unavailable', ['reason' => 'El pedido P2026000001 todavía no se ha exportado'], function (): void {
            $this->gatewayWith(FakeErpSoapClient::answering(3, 'El pedido P2026000001 todavía no se ha exportado'))->confirmShipment($this->confirmation());
        });
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function refusals(): iterable
    {
        yield 'no such order' => [2, 'El pedido P2026000001 no existe'];
        yield 'article not in the order' => [4, 'El artículo X no está en el pedido'];
        yield 'bad units' => [5, 'Unidades no válidas para el artículo X'];
        yield 'bad date' => [6, 'La fecha de envío debe ser dd/mm/aaaa'];
        yield 'incomplete data' => [7, 'Hacen falta el transportista y al menos un bulto'];
        yield 'a code nobody knows' => [99, 'Algo raro'];
    }

    #[DataProvider('refusals')]
    public function testARefusalIsNotWorthRetrying(int $code, string $message): void
    {
        self::assertDomainError('erp.shipment_rejected', ['reference' => 'P2026000001', 'code' => $code, 'detail' => $message], function () use ($code, $message): void {
            $this->gatewayWith(FakeErpSoapClient::answering($code, $message))->confirmShipment($this->confirmation());
        });
    }

    public function testAFaultOfTheServerCanBeTriedAgain(): void
    {
        $this->expectException(ErpUnavailable::class);

        $this->gatewayWith(new FakeErpSoapClient(new \SoapFault('Server', 'Internal error')))->confirmShipment($this->confirmation());
    }

    public function testAFaultThatBlamesTheMessageIsNotWorthRetrying(): void
    {
        self::assertDomainError('erp.shipment_rejected', ['reference' => 'P2026000001', 'code' => -1, 'detail' => 'Operación desconocida'], function (): void {
            $this->gatewayWith(new FakeErpSoapClient(new \SoapFault('soap:Client', 'Operación desconocida')))->confirmShipment($this->confirmation());
        });
    }

    public function testAConnectionThatFailsCanBeTriedAgain(): void
    {
        $this->expectException(ErpUnavailable::class);

        $this->gatewayWith(new FakeErpSoapClient(new \ErrorException('Could not connect to host')))->confirmShipment($this->confirmation());
    }

    public function testAnAnswerThatMakesNoSenseCanBeTriedAgain(): void
    {
        $this->expectException(ErpUnavailable::class);

        $this->gatewayWith(new FakeErpSoapClient(
            '<?xml version="1.0"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body><ConfirmarEnvioResponse xmlns="urn:marea:erp:v1"></ConfirmarEnvioResponse></soap:Body></soap:Envelope>',
        ))->confirmShipment($this->confirmation());
    }

    public function testAnErpThatIsDownIsReportedWhenItIsCalledAndNotBefore(): void
    {
        $down = new class implements SoapClientProvider {
            #[\Override]
            public function client(): \SoapClient
            {
                throw ErpUnavailable::because('the description of its service could not be read');
            }
        };

        // Building the gateway touches nothing: the provider is only asked when a call is made.
        $gateway = new SoapErpGateway($down);

        $this->expectException(ErpUnavailable::class);
        $gateway->confirmShipment($this->confirmation());
    }

    private function gatewayWith(\SoapClient $client): SoapErpGateway
    {
        return new SoapErpGateway(new readonly class($client) implements SoapClientProvider {
            public function __construct(private \SoapClient $client)
            {
            }

            #[\Override]
            public function client(): \SoapClient
            {
                return $this->client;
            }
        });
    }

    private function confirmation(?string $trackingNumber = 'TRK-99'): ShipmentConfirmation
    {
        return new ShipmentConfirmation(
            'P2026000001',
            new \DateTimeImmutable('2026-11-05T23:30:00+00:00'),
            'SEUR',
            $trackingNumber,
            2,
            [new ShippedLine('PV27-CAM-LIN-BLA-S', 3), new ShippedLine('PV27-PAN-CHI-ARE-40', 1)],
        );
    }
}
