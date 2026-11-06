<?php

declare(strict_types=1);

namespace App\Shared\Application;

/**
 * Something the people signed in should know now: a pick list was completed, units were not found.
 *
 * The id says which fact it is about and is the same every time that fact is announced, so a client
 * that receives it twice (the worker retries) can tell and show it once. The type is a stable name such as
 * `outbound.pick_list_completed`; the screen chooses the words, in the language of the person. The
 * data holds identifiers and numbers only, never a name or an email.
 */
final readonly class Notification
{
    /**
     * @param non-empty-string                     $id
     * @param non-empty-string                     $type
     * @param array<string, bool|float|int|string> $data
     */
    public function __construct(
        public string $id,
        public string $type,
        public array $data = [],
    ) {
    }
}
