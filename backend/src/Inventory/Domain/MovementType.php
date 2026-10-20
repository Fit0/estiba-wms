<?php

declare(strict_types=1);

namespace App\Inventory\Domain;

enum MovementType: string
{
    case Receipt = 'receipt';
    case Issue = 'issue';
    case TransferOut = 'transfer_out';
    case TransferIn = 'transfer_in';
    case Adjustment = 'adjustment';
}
