<?php

declare(strict_types=1);

namespace App\Identity\Domain;

use App\Identity\Domain\Event\TenantRegistered;
use App\Shared\Domain\AggregateRoot;
use App\Shared\Domain\Language;
use App\Shared\Domain\TenantId;

/**
 * A company that uses the system. Every other aggregate belongs to one, and the tenant is the
 * boundary of everything a user can see (ADR 0008).
 *
 * The default language is the one used where no request tells otherwise: background jobs and
 * printed documents (ADR 0004).
 */
final class Tenant extends AggregateRoot
{
    private function __construct(
        private readonly TenantId $id,
        private readonly TenantName $name,
        private readonly Language $defaultLanguage,
    ) {
    }

    public static function register(TenantId $id, TenantName $name, Language $defaultLanguage, \DateTimeImmutable $at): self
    {
        $tenant = new self($id, $name, $defaultLanguage);
        $tenant->record(new TenantRegistered($id, $name, $defaultLanguage, $at));

        return $tenant;
    }

    public function id(): TenantId
    {
        return $this->id;
    }

    public function name(): TenantName
    {
        return $this->name;
    }

    public function defaultLanguage(): Language
    {
        return $this->defaultLanguage;
    }
}
