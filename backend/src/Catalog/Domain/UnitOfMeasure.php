<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

/**
 * The base unit of a product: the smallest unit stock is counted in. Quantities in the
 * domain are whole numbers of this unit (DD-5).
 */
enum UnitOfMeasure: string
{
    case Each = 'each';
    case Pair = 'pair';
    case Set = 'set';
    case Kilogram = 'kilogram';
    case Litre = 'litre';
    case Metre = 'metre';
}
