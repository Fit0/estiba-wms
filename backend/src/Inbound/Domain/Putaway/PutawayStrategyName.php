<?php

declare(strict_types=1);

namespace App\Inbound\Domain\Putaway;

/**
 * The strategies a warehouse can choose from, by name, so the choice can live in configuration.
 */
enum PutawayStrategyName: string
{
    case FirstFree = 'first_free';
    case ByFamily = 'by_family';
    case ByTurnover = 'by_turnover';

    public function strategy(): PutawayStrategy
    {
        return match ($this) {
            self::FirstFree => new FirstFreeLocation(),
            self::ByFamily => new ByFamily(),
            self::ByTurnover => new ByTurnover(),
        };
    }
}
