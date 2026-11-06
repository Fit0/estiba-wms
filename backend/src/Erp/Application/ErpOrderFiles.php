<?php

declare(strict_types=1);

namespace App\Erp\Application;

use App\Erp\Domain\ErpOrder;
use App\Erp\Domain\Exception\MalformedOrderFile;

/**
 * Reads the files in which the ERP leaves its orders.
 */
interface ErpOrderFiles
{
    /**
     * @param string $contents the bytes of the file, as the ERP wrote them
     *
     * @return list<ErpOrder> the orders in the file, in the order they first appear
     *
     * @throws MalformedOrderFile
     */
    public function read(string $contents): array;
}
