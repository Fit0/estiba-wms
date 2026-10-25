<?php

declare(strict_types=1);

namespace App\Identity\Application\RegisterTenant;

use App\Identity\Domain\Email;
use App\Identity\Domain\Exception\InvalidLanguage;
use App\Identity\Domain\PasswordHasher;
use App\Identity\Domain\PlainPassword;
use App\Identity\Domain\Role;
use App\Identity\Domain\Tenant;
use App\Identity\Domain\TenantName;
use App\Identity\Domain\TenantRepository;
use App\Identity\Domain\User;
use App\Identity\Domain\UserId;
use App\Identity\Domain\UserRepository;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Application\Clock;
use App\Shared\Application\IdGenerator;
use App\Shared\Domain\Language;
use App\Shared\Domain\TenantId;

/**
 * The command bus owns the transaction and flushes, so the tenant and its administrator are saved
 * together or not at all.
 */
final readonly class RegisterTenantHandler implements CommandHandler
{
    public function __construct(
        private TenantRepository $tenants,
        private UserRepository $users,
        private PasswordHasher $hasher,
        private IdGenerator $ids,
        private EventBus $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(RegisterTenant $command): RegisteredTenant
    {
        $now = $this->clock->now();
        $language = Language::tryFrom(strtolower(trim($command->language))) ?? throw InvalidLanguage::forValue($command->language);

        $tenant = Tenant::register(TenantId::fromString($this->ids->next()), TenantName::fromString($command->name), $language, $now);
        $admin = User::register(
            UserId::fromString($this->ids->next()),
            $tenant->id(),
            Email::fromString($command->adminEmail),
            $this->hasher->hash(PlainPassword::fromString($command->adminPassword)),
            [Role::Admin],
            $this->users,
            $now,
        );

        $this->tenants->save($tenant);
        $this->users->save($admin);

        $this->events->publish(...$tenant->pullEvents(), ...$admin->pullEvents());

        return new RegisteredTenant($tenant, $admin);
    }
}
