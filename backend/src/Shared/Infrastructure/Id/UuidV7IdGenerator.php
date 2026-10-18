<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Id;

use App\Shared\Application\IdGenerator;
use Symfony\Component\Uid\Uuid;

final readonly class UuidV7IdGenerator implements IdGenerator
{
    #[\Override]
    public function next(): string
    {
        return Uuid::v7()->toRfc4122();
    }
}
