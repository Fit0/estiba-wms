<?php

declare(strict_types=1);

namespace App\Tests\Support;

/**
 * A SOAP client that reads the WSDL of the ERP from a file and answers with the XML it is given instead
 * of calling anyone. It keeps the last request, to see what the gateway sent.
 *
 * The WSDL in tests/Support/erp.wsdl is a copy of erp-legacy/inc/erp.wsdl: change both together.
 */
final class FakeErpSoapClient extends \SoapClient
{
    public ?string $lastRequest = null;

    /**
     * @param string|\Throwable $answer the body of the SOAP answer, or what to throw instead of answering
     */
    public function __construct(private readonly string|\Throwable $answer)
    {
        parent::__construct(__DIR__.'/erp.wsdl', ['exceptions' => true, 'cache_wsdl' => \WSDL_CACHE_NONE, 'trace' => false]);
    }

    /**
     * An answer of the ERP to ConfirmarEnvio, with the code and the message it carries.
     */
    public static function answering(int $code, string $message): self
    {
        return new self(
            '<?xml version="1.0" encoding="UTF-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body>'
            .'<ConfirmarEnvioResponse xmlns="urn:marea:erp:v1"><Codigo>'.$code.'</Codigo><Mensaje>'.htmlspecialchars($message).'</Mensaje></ConfirmarEnvioResponse>'
            .'</soap:Body></soap:Envelope>',
        );
    }

    #[\Override]
    public function __doRequest(string $request, string $location, string $action, int $version, bool $oneWay = false, ?string $uriParserClass = null): string
    {
        $this->lastRequest = $request;

        if ($this->answer instanceof \Throwable) {
            throw $this->answer;
        }

        return $this->answer;
    }
}
