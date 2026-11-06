<?php

declare(strict_types=1);

namespace App\Erp\Infrastructure\Soap;

use App\Erp\Application\ConfirmationOutcome;
use App\Erp\Application\ErpGateway;
use App\Erp\Application\ShipmentConfirmation;
use App\Erp\Application\ShippedLine;
use App\Erp\Domain\Exception\ErpRejectedShipment;
use App\Erp\Domain\Exception\ErpUnavailable;

/**
 * Speaks to the ERP through its SOAP service (erp-legacy/README.md has the contract). Everything of the
 * ERP's language stays in this class: its operation, its names, its dates written day first, and the
 * codes with which it answers instead of raising faults.
 *
 * A code that means "try later" becomes {@see ErpUnavailable} and one that means "no" becomes
 * {@see ErpRejectedShipment}, so the caller decides what to do from the type of the exception and does not
 * need to know the codes.
 */
final readonly class SoapErpGateway implements ErpGateway
{
    private const string TIME_ZONE = 'Europe/Madrid';

    private const int CODE_OK = 0;

    private const int CODE_ALREADY_CONFIRMED = 1;

    /** The order exists but its file has not been written yet: it will be. */
    private const int CODE_NOT_EXPORTED = 3;

    /** Stands for a SOAP fault that blames the message, which the ERP has no code for. */
    private const int CODE_FAULT = -1;

    public function __construct(private SoapClientProvider $clients)
    {
    }

    #[\Override]
    public function confirmShipment(ShipmentConfirmation $confirmation): ConfirmationOutcome
    {
        $client = $this->clients->client();

        try {
            $response = $client->ConfirmarEnvio($this->requestOf($confirmation));
        } catch (\SoapFault $fault) {
            throw $this->failureOf($confirmation, $fault);
        } catch (\ErrorException $error) {
            // A connection that fails is reported by PHP as a warning before the fault.
            throw ErpUnavailable::because($error->getMessage());
        }

        // The WSDL says the code is an int, and ext-soap hands it over as one.
        if (!$response instanceof \stdClass || !isset($response->Codigo) || !\is_int($response->Codigo)) {
            throw ErpUnavailable::because('its answer to the confirmation could not be understood');
        }

        $code = $response->Codigo;
        $message = isset($response->Mensaje) && \is_string($response->Mensaje) ? $response->Mensaje : '';

        return match ($code) {
            self::CODE_OK => ConfirmationOutcome::Confirmed,
            self::CODE_ALREADY_CONFIRMED => ConfirmationOutcome::AlreadyConfirmed,
            self::CODE_NOT_EXPORTED => throw ErpUnavailable::because($message),
            default => throw ErpRejectedShipment::withCode($confirmation->orderReference, $code, $message),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function requestOf(ShipmentConfirmation $confirmation): array
    {
        $request = [
            'NumeroPedido' => $confirmation->orderReference,
            'FechaEnvio' => $confirmation->shippedAt->setTimezone(new \DateTimeZone(self::TIME_ZONE))->format('d/m/Y'),
            'Transportista' => $confirmation->carrier,
            'Bultos' => $confirmation->parcels,
            'Lineas' => ['Linea' => array_map(
                static fn (ShippedLine $line): array => ['Articulo' => $line->sku, 'Unidades' => $line->units],
                $confirmation->lines,
            )],
        ];

        if (null !== $confirmation->trackingNumber) {
            $request['Seguimiento'] = $confirmation->trackingNumber;
        }

        return $request;
    }

    private function failureOf(ShipmentConfirmation $confirmation, \SoapFault $fault): ErpUnavailable|ErpRejectedShipment
    {
        // The ERP raises a fault only for a message it cannot read, which sending again will not mend.
        // Any other fault, and a connection that does not answer, are a matter of time.
        return str_ends_with((string) $fault->faultcode, 'Client')
            ? ErpRejectedShipment::withCode($confirmation->orderReference, self::CODE_FAULT, $fault->getMessage())
            : ErpUnavailable::because($fault->getMessage());
    }
}
