<?php

declare(strict_types=1);

namespace App\Erp\Infrastructure\Soap;

use App\Erp\Domain\Exception\ErpUnavailable;

/**
 * Gives the SOAP client of the ERP. The client is not made until it is asked for, because making it
 * reads the WSDL from the ERP, and an ERP that is down must not stop the application from starting.
 */
interface SoapClientProvider
{
    /**
     * @throws ErpUnavailable when the WSDL cannot be read
     */
    public function client(): \SoapClient;
}
