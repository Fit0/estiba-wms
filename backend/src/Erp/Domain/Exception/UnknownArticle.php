<?php

declare(strict_types=1);

namespace App\Erp\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * An order of the ERP names an article that is not a product of the catalogue.
 */
final class UnknownArticle extends DomainRuleViolation
{
    public static function forSku(string $sku): self
    {
        return new self(
            'erp.article_unknown',
            ['sku' => $sku],
            \sprintf('The ERP order names article %s, which is not in the catalogue.', $sku),
        );
    }
}
