<?php

declare(strict_types=1);

namespace App\Erp\Domain;

/**
 * A customer order as the ERP knows it, read from one of its files and not yet translated. The
 * number is the ERP's, and it stays the reference of the order in Estiba.
 */
final readonly class ErpOrder
{
    /**
     * @param \DateTimeImmutable $dueAt the moment the order must have left, with the time zone of the ERP
     * @param list<ErpOrderLine> $lines in the order of their number
     */
    public function __construct(
        public string $number,
        public \DateTimeImmutable $takenOn,
        public string $customer,
        public string $street,
        public string $postalCode,
        public string $city,
        public string $countryCode,
        public ErpUrgency $urgency,
        public \DateTimeImmutable $dueAt,
        public array $lines,
    ) {
    }
}
