<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Shared\Infrastructure\Realtime\HubTransport;

/**
 * A hub that remembers what it was sent, or refuses everything.
 */
final class RecordingHubTransport implements HubTransport
{
    /** @var list<array{url: string, form: array<string, string>, token: string}> */
    public array $published = [];

    public function __construct(private readonly bool $fail = false)
    {
    }

    #[\Override]
    public function publish(string $hubUrl, array $form, string $bearerToken): void
    {
        if ($this->fail) {
            throw new \RuntimeException('The Mercure hub answered 500.');
        }

        $this->published[] = ['url' => $hubUrl, 'form' => $form, 'token' => $bearerToken];
    }
}
