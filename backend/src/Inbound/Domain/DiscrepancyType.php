<?php

declare(strict_types=1);

namespace App\Inbound\Domain;

enum DiscrepancyType: string
{
    /** Fewer units arrived than the receipt expected. */
    case Shortage = 'shortage';

    /** More units arrived than the receipt expected (INB-08). */
    case Excess = 'excess';

    /** A product arrived that is not on the ASN (INB-06). */
    case UnexpectedProduct = 'unexpected_product';

    /** Units arrived damaged (INB-10). */
    case Damaged = 'damaged';

    /**
     * Whether the receipt holds more than the ASN announced, which needs a supervisor's
     * approval before closing (INB-08).
     */
    public function isOverReceipt(): bool
    {
        return self::Excess === $this || self::UnexpectedProduct === $this;
    }
}
