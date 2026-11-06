<?php

declare(strict_types=1);

namespace App\Erp\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * A file of orders of the ERP does not follow the format: a missing column, a date that is not a date,
 * a quantity that is not a number. The field is the name of the ERP's column, so it needs no translation.
 */
final class MalformedOrderFile extends DomainRuleViolation
{
    public static function at(int $line, string $field): self
    {
        return new self(
            'erp.order_file_malformed',
            ['line' => $line, 'field' => $field],
            \sprintf('The order file is not valid at line %d, in %s.', $line, $field),
        );
    }
}
