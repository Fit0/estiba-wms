<?php

declare(strict_types=1);

namespace App\Erp\Domain;

/**
 * How the ERP says an order is urgent: a single letter. It is the ERP's word, and only this context
 * knows it (ADR 0047).
 */
enum ErpUrgency: string
{
    case Low = 'B';
    case Normal = 'N';
    case High = 'A';
    case Urgent = 'U';
}
