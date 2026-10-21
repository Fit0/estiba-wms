<?php

declare(strict_types=1);

namespace App\Inbound\Infrastructure\Persistence\Doctrine\Type;

use App\Inbound\Domain\ReceiptId;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\IdentifierType;

final class ReceiptIdType extends IdentifierType
{
    public const string NAME = 'receipt_id';

    #[\Override]
    protected function fromDatabase(string $value): ReceiptId
    {
        return ReceiptId::fromString($value);
    }
}
