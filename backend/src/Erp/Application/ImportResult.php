<?php

declare(strict_types=1);

namespace App\Erp\Application;

/**
 * What became of a file of orders of the ERP.
 */
enum ImportResult: string
{
    case Processed = 'processed';
    case Rejected = 'rejected';
}
