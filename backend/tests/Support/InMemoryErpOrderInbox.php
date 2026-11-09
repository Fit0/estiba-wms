<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Erp\Application\ErpOrderInbox;
use App\Erp\Domain\Exception\ErpUnavailable;

/**
 * An inbox that holds its files in memory, for the tests of the import.
 */
final class InMemoryErpOrderInbox implements ErpOrderInbox
{
    /** @var array<string, string> */
    public array $waiting = [];

    /** @var list<string> */
    public array $processed = [];

    /** @var list<string> */
    public array $rejected = [];

    public bool $down = false;

    #[\Override]
    public function pending(): array
    {
        $this->assertUp();
        $names = array_keys($this->waiting);
        sort($names);

        return $names;
    }

    #[\Override]
    public function read(string $name): string
    {
        $this->assertUp();

        return $this->waiting[$name] ?? throw ErpUnavailable::because('there is no file '.$name);
    }

    #[\Override]
    public function markProcessed(string $name): void
    {
        $this->assertUp();
        unset($this->waiting[$name]);
        $this->processed[] = $name;
    }

    #[\Override]
    public function markRejected(string $name): void
    {
        $this->assertUp();
        unset($this->waiting[$name]);
        $this->rejected[] = $name;
    }

    private function assertUp(): void
    {
        if ($this->down) {
            throw ErpUnavailable::because('the inbox is down');
        }
    }
}
