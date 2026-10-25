<?php

declare(strict_types=1);

namespace App\Outbound\Domain;

enum ShipmentStatus: string
{
    case Packing = 'packing';
    case Packed = 'packed';
    case Dispatched = 'dispatched';
}
