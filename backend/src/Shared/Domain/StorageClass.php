<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * How goods must be stored. A product can only go to a zone of a compatible class.
 * Catalog and Warehouse both use it, so it lives in the shared kernel.
 */
enum StorageClass: string
{
    case Ambient = 'ambient';
    case Chilled = 'chilled';
    case Frozen = 'frozen';
    case Hazmat = 'hazmat';
}
