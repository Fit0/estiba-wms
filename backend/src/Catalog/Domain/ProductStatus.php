<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

enum ProductStatus: string
{
    case Active = 'active';

    /** Cannot be received or allocated. Can be unblocked (CAT-06). */
    case Blocked = 'blocked';

    /** Withdrawn from the range for good (CAT-08). */
    case Discontinued = 'discontinued';
}
