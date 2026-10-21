<?php

declare(strict_types=1);

namespace App\Inbound\Infrastructure\Http\Input;

/**
 * The location where the goods now are, and why, when it is not the suggested one.
 */
final readonly class ConfirmPutawayInput
{
    public function __construct(
        public string $locationId,
        public ?string $reason = null,
    ) {
    }
}
