<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

enum PackagingType: string
{
    case InnerPack = 'inner_pack';
    case Case = 'case';
    case Pallet = 'pallet';
}
