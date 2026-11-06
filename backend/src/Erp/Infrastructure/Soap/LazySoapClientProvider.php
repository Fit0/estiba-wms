<?php

declare(strict_types=1);

namespace App\Erp\Infrastructure\Soap;

use App\Erp\Domain\Exception\ErpUnavailable;

/**
 * Makes the client the first time it is needed and keeps it for the rest of the process.
 */
final class LazySoapClientProvider implements SoapClientProvider
{
    private const int CONNECTION_TIMEOUT_SECONDS = 5;

    private const int RESPONSE_TIMEOUT_SECONDS = 15;

    private ?\SoapClient $client = null;

    public function __construct(private readonly string $wsdl)
    {
    }

    #[\Override]
    public function client(): \SoapClient
    {
        return $this->client ??= $this->connect();
    }

    private function connect(): \SoapClient
    {
        try {
            return new \SoapClient($this->wsdl, [
                'exceptions' => true,
                'connection_timeout' => self::CONNECTION_TIMEOUT_SECONDS,
                'cache_wsdl' => \WSDL_CACHE_MEMORY,
                'user_agent' => 'Estiba WMS',
                'stream_context' => stream_context_create(['http' => ['timeout' => self::RESPONSE_TIMEOUT_SECONDS]]),
            ]);
        } catch (\SoapFault|\ErrorException $error) {
            // A WSDL that cannot be loaded is reported as a warning first and as a fault second.
            throw ErpUnavailable::because('the description of its service could not be read ('.$error->getMessage().')');
        }
    }
}
